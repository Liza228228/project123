#!/usr/bin/env python3
"""
Очистка SQL-дампа phpMyAdmin для импорта в MySQL Workbench.

Удаляет комментарии с ошибками экспорта phpMyAdmin и (опционально)
оставляет только базы, где есть CREATE TABLE.

Примеры:
  python scripts/clean_phpmyadmin_dump.py dump.sql
  python scripts/clean_phpmyadmin_dump.py dump.sql -o dump_clean.sql
  python scripts/clean_phpmyadmin_dump.py dump.sql --only-with-tables
  python scripts/clean_phpmyadmin_dump.py dump.sql --split-dir Data/sql_parts
  python scripts/clean_phpmyadmin_dump.py dump.sql --only-with-tables --drop-existing
  python scripts/clean_phpmyadmin_dump.py dump.sql --database er-model --schema-only --for-workbench-model
  python scripts/clean_phpmyadmin_dump.py dump.sql --only-with-tables --import
"""

from __future__ import annotations

import argparse
import html
import re
import shutil
import subprocess
import sys
from pathlib import Path

ERROR_LINE_MARKERS = (
    "Структура чтения ошибок для таблицы",
    "Ошибка считывания данных таблицы",
)
ERROR_CODE_RE = re.compile(r"#\d{4}\s*-")
ERROR_ENGINE_RE = re.compile(r"doesn.?t exist in engine", re.IGNORECASE)

CREATE_DATABASE_RE = re.compile(
    r"^CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`([^`]+)`",
    re.IGNORECASE | re.MULTILINE,
)
CREATE_TABLE_RE = re.compile(r"^\s*CREATE\s+TABLE\s+", re.IGNORECASE | re.MULTILINE)
TABLE_NAME_IN_DDL_RE = re.compile(r"`([^`]+)`")
LARAVEL_NOISE_TABLES = frozenset({
    "cache",
    "cache_locks",
    "failed_jobs",
    "jobs",
    "job_batches",
    "migrations",
    "password_reset_tokens",
    "sessions",
})
CORE_ER_TABLES = (
    "users",
    "roles",
    "subdivisions",
    "warehouses",
    "warehouse_types",
    "applications",
    "application_items",
    "application_statuses",
    "transport_options",
)
INDEXES_SECTION_RE = re.compile(
    r"\n--\s*\n--\s*Индексы сохранённых таблиц[\s\S]*\Z",
    re.IGNORECASE,
)
DATA_LINE_RE = re.compile(
    r"^\s*(INSERT\s+INTO|LOCK\s+TABLES|UNLOCK\s+TABLES|"
    r"/\*!40000\s+ALTER\s+TABLE\s+`[^`]+`\s+(?:DISABLE|ENABLE)\s+KEYS\s*\*/)",
    re.IGNORECASE,
)
INSERT_START_RE = re.compile(r"^\s*INSERT\s+INTO\b", re.IGNORECASE)
INSERT_VALUE_LINE_RE = re.compile(r"^\s*\(")
DATA_DUMP_COMMENT_RE = re.compile(r"^\s*--\s*Дамп данных таблицы", re.IGNORECASE)


def is_data_statement(line: str) -> bool:
    return DATA_LINE_RE.match(line) is not None


def is_insert_value_line(line: str) -> bool:
    if not INSERT_VALUE_LINE_RE.match(line):
        return False
    return not CREATE_TABLE_RE.match(line)


def strip_data_statements(text: str) -> tuple[str, int]:
    kept: list[str] = []
    removed = 0
    in_insert = False

    for line in text.splitlines(keepends=True):
        stripped = line.strip()

        if is_data_statement(line):
            removed += 1
            if INSERT_START_RE.match(line):
                in_insert = True
                if stripped.endswith(";"):
                    in_insert = False
            continue

        if in_insert:
            removed += 1
            if stripped.endswith(";"):
                in_insert = False
            continue

        if is_insert_value_line(line):
            removed += 1
            if stripped.endswith(";"):
                in_insert = False
            continue

        if DATA_DUMP_COMMENT_RE.match(line):
            removed += 1
            continue

        kept.append(line)

    return "".join(kept), removed


def is_error_comment(line: str) -> bool:
    stripped = line.strip()
    if not stripped.startswith("--"):
        return False

    if any(marker in stripped for marker in ERROR_LINE_MARKERS):
        return True

    if ERROR_CODE_RE.search(stripped):
        return True

    if ERROR_ENGINE_RE.search(stripped):
        return True

    return False


def decode_html_entities(text: str) -> str:
    return html.unescape(text)


def split_into_database_blocks(content: str) -> tuple[str, list[tuple[str, str]]]:
    """Возвращает (преамбула, [(имя_базы, sql_фрагмент), ...])."""
    matches = list(CREATE_DATABASE_RE.finditer(content))
    if not matches:
        return "", [("", content)]

    preamble = content[: matches[0].start()]
    blocks: list[tuple[str, str]] = []

    for index, match in enumerate(matches):
        db_name = match.group(1)
        start = match.start()
        end = matches[index + 1].start() if index + 1 < len(matches) else len(content)
        blocks.append((db_name, content[start:end]))

    return preamble, blocks


def clean_lines(text: str) -> tuple[str, int]:
    kept: list[str] = []
    removed = 0

    for line in text.splitlines(keepends=True):
        if is_error_comment(line):
            removed += 1
            continue
        kept.append(line)

    return "".join(kept), removed


def exclude_tables_from_block(text: str, table_names: set[str]) -> tuple[str, int]:
    if not table_names:
        return text, 0

    removed = 0
    result = text

    for name in table_names:
        create_pattern = (
            rf"(?:--[^\n]*\n)*CREATE TABLE `{re.escape(name)}`\s*\("
            rf"[\s\S]*?\)\s*ENGINE=[^;]+;\s*"
        )
        result, count = re.subn(create_pattern, "", result, flags=re.IGNORECASE)
        removed += count

        alter_pattern = rf"ALTER TABLE `{re.escape(name)}`[\s\S]*?;\s*"
        result, count = re.subn(alter_pattern, "", result, flags=re.IGNORECASE)
        removed += count

    return result, removed


def parse_table_list(value: str) -> set[str]:
    return {part.strip() for part in value.split(",") if part.strip()}


def default_lite_tables(block: str) -> set[str]:
    present = set(TABLE_NAME_IN_DDL_RE.findall(block))
    return LARAVEL_NOISE_TABLES & present


def strip_indexes_and_alters(text: str) -> tuple[str, int]:
    match = INDEXES_SECTION_RE.search(text)
    if match:
        return text[: match.start()] + "\n", 1

    result, count = re.subn(r"\nALTER TABLE[\s\S]*?;\s*", "\n", text)
    result, extra = re.subn(
        r"\n--\s*\n--\s*Индексы таблицы[\s\S]*?(?=\n--\s*\n--\s*Индексы таблицы|\nALTER TABLE|\Z)",
        "\n",
        result,
        flags=re.IGNORECASE,
    )
    return result, count + extra


def keep_only_tables(block: str, table_names: set[str]) -> tuple[str, set[str]]:
    present = {
        name
        for name in TABLE_NAME_IN_DDL_RE.findall(block)
        if re.search(rf"CREATE TABLE `{re.escape(name)}`", block, re.IGNORECASE)
    }
    to_remove = present - table_names
    cleaned, _ = exclude_tables_from_block(block, to_remove)
    return cleaned, to_remove


def block_has_tables(block: str) -> bool:
    return CREATE_TABLE_RE.search(block) is not None


def count_tables(block: str) -> int:
    return len(CREATE_TABLE_RE.findall(block))


def list_databases(raw: str) -> list[tuple[str, int, bool]]:
    """Возвращает [(имя_базы, число_таблиц, есть_ошибки_экспорта), ...]."""
    _, db_blocks = split_into_database_blocks(raw)
    result: list[tuple[str, int, bool]] = []

    for db_name, block in db_blocks:
        if not db_name:
            continue
        cleaned, _ = clean_lines(block)
        table_count = count_tables(cleaned)
        has_export_errors = any(
            marker in block for marker in ERROR_LINE_MARKERS
        ) or ERROR_ENGINE_RE.search(block) is not None
        result.append((db_name, table_count, has_export_errors))

    return result


def print_database_list(databases: list[tuple[str, int, bool]]) -> None:
    with_tables = [(name, count) for name, count, _ in databases if count > 0]
    broken = [name for name, count, has_errors in databases if count == 0 and has_errors]
    empty = [name for name, count, has_errors in databases if count == 0 and not has_errors]

    print(f"Баз в дампе: {len(databases)}")
    print(f"  с CREATE TABLE: {len(with_tables)}")
    if broken:
        print(f"  повреждены (нет CREATE TABLE, ошибки phpMyAdmin): {len(broken)}")
    if empty:
        print(f"  без таблиц: {len(empty)}")
    print()

    if with_tables:
        print("Базы, из которых можно построить ER-диаграмму:")
        for name, count in with_tables:
            print(f"  - {name} ({count} таблиц)")
        print()

    if broken:
        print("Повреждённые базы (таблицы не экспортировались):")
        preview = ", ".join(broken[:15])
        if len(broken) > 15:
            preview += ", ..."
        print(f"  {preview}")
        print()
        print("Для таких баз ER-диаграмму из этого дампа не построить.")
        print("Выберите другую базу из списка выше или экспортируйте схему заново.")


def add_drop_database(block: str, db_name: str) -> str:
    if not db_name:
        return block

    drop_stmt = f"DROP DATABASE IF EXISTS `{db_name}`;\n"
    match = CREATE_DATABASE_RE.search(block)
    if match is None:
        return drop_stmt + block

    return block[: match.start()] + drop_stmt + block[match.start() :]


def default_output_path(input_path: Path) -> Path:
    return input_path.with_name(f"{input_path.stem}_clean{input_path.suffix or '.sql'}")


def run_mysql_import(
    sql_file: Path,
    host: str,
    port: int,
    user: str,
    password: str,
    mysql_bin: str,
) -> None:
    mysql_path = shutil.which(mysql_bin) or mysql_bin
    if not Path(mysql_path).exists() and shutil.which(mysql_bin) is None:
        raise FileNotFoundError(
            f"mysql не найден: {mysql_bin}. "
            "Укажите путь, например: C:\\xampp\\mysql\\bin\\mysql.exe"
        )

    cmd = [
        mysql_path,
        f"-h{host}",
        f"-P{port}",
        f"-u{user}",
        "--default-character-set=utf8mb4",
    ]

    env = None
    if password:
        import os

        env = os.environ.copy()
        env["MYSQL_PWD"] = password

    print(f"Импорт через: {mysql_path}")
    subprocess.run(
        cmd,
        input=sql_file.read_text(encoding="utf-8"),
        text=True,
        check=True,
        env=env,
    )


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Очистка SQL-дампа phpMyAdmin для импорта в MySQL Workbench.",
    )
    parser.add_argument("input", type=Path, help="Исходный .sql файл из phpMyAdmin")
    parser.add_argument(
        "-o",
        "--output",
        type=Path,
        help="Путь к очищенному файлу (по умолчанию: <имя>_clean.sql)",
    )
    parser.add_argument(
        "--only-with-tables",
        action="store_true",
        help="Оставить только базы с CREATE TABLE",
    )
    parser.add_argument(
        "--split-dir",
        type=Path,
        help="Разбить результат на отдельные .sql файлы по базам",
    )
    parser.add_argument(
        "--database",
        help="Оставить только одну базу, например: er-model или Diplom",
    )
    parser.add_argument(
        "--schema-only",
        action="store_true",
        help="Удалить INSERT/LOCK TABLES — только DDL для ER-диаграммы",
    )
    parser.add_argument(
        "--for-workbench-model",
        action="store_true",
        help="Режим для EER-диаграммы: включает --schema-only и подсказки Reverse Engineer",
    )
    parser.add_argument(
        "--drop-existing",
        action="store_true",
        help="Добавить DROP DATABASE IF EXISTS перед каждой базой (для повторного импорта)",
    )
    parser.add_argument(
        "--import",
        dest="do_import",
        action="store_true",
        help="Импортировать очищенный дамп через mysql.exe",
    )
    parser.add_argument("--host", default="127.0.0.1", help="Хост MySQL (по умолчанию: 127.0.0.1)")
    parser.add_argument("--port", type=int, default=3306, help="Порт MySQL (по умолчанию: 3306)")
    parser.add_argument("--user", default="root", help="Пользователь MySQL (по умолчанию: root)")
    parser.add_argument("--password", default="", help="Пароль MySQL")
    parser.add_argument(
        "--mysql-bin",
        default="mysql",
        help="Путь к mysql.exe (по умолчанию: mysql из PATH)",
    )
    parser.add_argument(
        "--list-databases",
        action="store_true",
        help="Показать базы в дампе и сколько в каждой CREATE TABLE",
    )
    parser.add_argument(
        "--lite",
        action="store_true",
        help="Убрать служебные Laravel-таблицы (cache, jobs, sessions и т.д.) для стабильной ER-диаграммы",
    )
    parser.add_argument(
        "--exclude-tables",
        help="Не включать таблицы (через запятую), например: cache,sessions,migrations",
    )
    parser.add_argument(
        "--only-tables",
        help="Оставить только эти таблицы (через запятую)",
    )
    parser.add_argument(
        "--minimal",
        action="store_true",
        help="Только ключевые таблицы для ER-диаграммы (~9 шт.)",
    )
    parser.add_argument(
        "--create-only",
        action="store_true",
        help="Убрать ALTER TABLE / индексы / FK (стабильнее для Workbench)",
    )
    return parser.parse_args()


def process_dump(
    raw: str,
    only_with_tables: bool,
    database: str | None = None,
    schema_only: bool = False,
    exclude_tables: set[str] | None = None,
    only_tables: set[str] | None = None,
    lite: bool = False,
    create_only: bool = False,
) -> tuple[str, list[tuple[str, str]], int, list[str]]:
    preamble, db_blocks = split_into_database_blocks(raw)

    preamble_cleaned, preamble_removed = clean_lines(preamble)
    total_removed = preamble_removed
    cleaned_blocks: list[tuple[str, str]] = []
    skipped_databases: list[str] = []

    for db_name, block in db_blocks:
        if database and db_name and db_name != database:
            skipped_databases.append(db_name)
            continue

        cleaned, removed = clean_lines(block)
        total_removed += removed

        if schema_only:
            cleaned, data_removed = strip_data_statements(cleaned)
            total_removed += data_removed

        tables_to_exclude = set(exclude_tables or ())
        if lite:
            tables_to_exclude |= default_lite_tables(cleaned)
        if tables_to_exclude:
            cleaned, ddl_removed = exclude_tables_from_block(cleaned, tables_to_exclude)
            total_removed += ddl_removed

        if only_tables:
            cleaned, removed_names = keep_only_tables(cleaned, only_tables)
            total_removed += len(removed_names)

        if create_only:
            cleaned, alter_removed = strip_indexes_and_alters(cleaned)
            total_removed += alter_removed

        if only_with_tables and db_name and not block_has_tables(cleaned):
            skipped_databases.append(db_name)
            continue

        cleaned_blocks.append((db_name, cleaned))

    return preamble_cleaned, cleaned_blocks, total_removed, skipped_databases


def print_summary(
    cleaned_blocks: list[tuple[str, str]],
    total_removed: int,
    skipped_databases: list[str],
    output_path: Path | None = None,
    for_workbench_model: bool = False,
    database: str | None = None,
) -> None:
    if output_path is not None:
        print(f"Готово: {output_path}")
        print(f"Баз данных в файле: {len(cleaned_blocks)}")
        print(f"Удалено строк с ошибками phpMyAdmin: {total_removed}")

    if skipped_databases and not database:
        print(f"Пропущено пустых баз (--only-with-tables): {len(skipped_databases)}")
        preview = ", ".join(skipped_databases[:10])
        if len(skipped_databases) > 10:
            preview += ", ..."
        print(f"  {preview}")

    databases_with_tables = [
        name for name, block in cleaned_blocks if name and block_has_tables(block)
    ]
    if databases_with_tables:
        print("Базы с таблицами:")
        for name in databases_with_tables:
            print(f"  - {name}")

    print()
    if for_workbench_model:
        target = database or (databases_with_tables[0] if databases_with_tables else "ваша_база")
        print("EER-диаграмма в MySQL Workbench (вкладка Model) — стабильный способ:")
        print("  1. File -> New Model")
        print("  2. Database -> Reverse Engineer SQL Script...")
        print(f"  3. Укажите этот файл -> Next -> Execute -> Finish")
        print("     (SQL на сервер выполнять НЕ нужно — Workbench читает файл напрямую.)")
        print("  4. Model -> Create Diagram from Catalog Objects... -> выберите таблицы -> OK")
        print()
        print("Если Workbench закрывается на Execute:")
        print("  - используйте *_mini_schema.sql (--minimal --create-only)")
        print("  - File -> Import -> Reverse Engineer MySQL Create Script...")
        print("  - добавляйте таблицы на диаграмму по 3–5 штук")
        print()
        print("Альтернатива через сервер:")
        print("  File -> Open SQL Script -> Execute, затем Database -> Reverse Engineer...")
        print(f"  и выберите базу `{target}`.")
        print()
        print("Важно: Data Import не строит ER-диаграмму.")
    else:
        print("Импорт в MySQL Workbench:")
        print("  1. Подключитесь к серверу (127.0.0.1).")
        print("  2. Server -> Data Import -> Import from Self-Contained File.")
        print("  3. Укажите очищенный .sql файл -> Start Import.")
        print("  Или: File -> Open SQL Script -> Execute (молния).")


def main() -> int:
    args = parse_args()
    input_path: Path = args.input

    if not input_path.is_file():
        print(f"Файл не найден: {input_path}", file=sys.stderr)
        return 1

    raw = decode_html_entities(
        input_path.read_text(encoding="utf-8", errors="replace")
    )

    if args.list_databases:
        print_database_list(list_databases(raw))
        return 0

    schema_only = args.schema_only or args.for_workbench_model
    exclude_tables = parse_table_list(args.exclude_tables) if args.exclude_tables else None
    only_tables: set[str] | None = None
    if args.only_tables:
        only_tables = parse_table_list(args.only_tables)
    elif args.minimal:
        only_tables = set(CORE_ER_TABLES)

    preamble_cleaned, cleaned_blocks, total_removed, skipped_databases = process_dump(
        raw,
        args.only_with_tables,
        database=args.database,
        schema_only=schema_only,
        exclude_tables=exclude_tables,
        only_tables=only_tables,
        lite=args.lite,
        create_only=args.create_only or args.minimal,
    )

    if args.database and not cleaned_blocks:
        print(
            f"База `{args.database}` не найдена в дампе.",
            file=sys.stderr,
        )
        return 1

    if args.database:
        _, blocks = cleaned_blocks[0]
        if not block_has_tables(blocks):
            print(
                f"База `{args.database}` в дампе повреждена: нет CREATE TABLE "
                f"(ошибка phpMyAdmin «doesn't exist in engine»).",
                file=sys.stderr,
            )
            print(
                "Запустите с --list-databases, чтобы увидеть рабочие базы в этом файле.",
                file=sys.stderr,
            )
            return 1

    if not cleaned_blocks:
        print("После фильтрации не осталось данных для импорта.", file=sys.stderr)
        return 1

    if args.for_workbench_model and not args.drop_existing:
        args.drop_existing = True

    output_path: Path | None = None

    if args.split_dir:
        args.split_dir.mkdir(parents=True, exist_ok=True)
        written_files: list[Path] = []

        for index, (db_name, block) in enumerate(cleaned_blocks):
            suffix = db_name or "preamble"
            out_file = args.split_dir / f"{suffix}.sql"
            content = (preamble_cleaned if index == 0 else "") + block
            if args.drop_existing:
                content = add_drop_database(content, db_name)
            out_file.write_text(content, encoding="utf-8")
            written_files.append(out_file)

        print(f"Создано файлов: {len(written_files)} в {args.split_dir}")
        for path in written_files:
            print(f"  - {path.name}")

        if args.do_import:
            print("Импорт из --split-dir не поддерживается. Укажите -o и один файл.", file=sys.stderr)
            return 1
    else:
        output_path = args.output or default_output_path(input_path)
        parts: list[str] = [preamble_cleaned]
        for db_name, block in cleaned_blocks:
            parts.append(add_drop_database(block, db_name) if args.drop_existing else block)
        merged = "".join(parts)
        output_path.write_text(merged, encoding="utf-8")

    print_summary(
        cleaned_blocks,
        total_removed,
        skipped_databases,
        output_path,
        for_workbench_model=args.for_workbench_model,
        database=args.database,
    )

    if args.do_import:
        if output_path is None:
            print("Для --import нужен один выходной файл, не --split-dir.", file=sys.stderr)
            return 1

        try:
            run_mysql_import(
                output_path,
                host=args.host,
                port=args.port,
                user=args.user,
                password=args.password,
                mysql_bin=args.mysql_bin,
            )
            print("Импорт завершён.")
        except (FileNotFoundError, subprocess.CalledProcessError) as exc:
            print(f"Ошибка импорта: {exc}", file=sys.stderr)
            return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
