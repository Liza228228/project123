#!/usr/bin/env python3
"""
Готовит один SQL-файл для ER-диаграммы в MySQL Workbench (все таблицы, без вылетов).

Что делает:
  - чистит дамп phpMyAdmin;
  - убирает INSERT и лишние ALTER (AUTO_INCREMENT);
  - встраивает PRIMARY KEY, INDEX и FOREIGN KEY прямо в CREATE TABLE
    (Workbench стабильнее читает такой файл, чем сотни отдельных ALTER).

Примеры:
  py scripts/generate_workbench_eer.py C:\\Users\\Adminium\\Downloads\\127_0_0_1.sql
  py scripts/generate_workbench_eer.py dump.sql --database diplom
  py scripts/generate_workbench_eer.py dump.sql --database diplom -o C:\\Downloads\\diplom_eer.sql
  py scripts/generate_workbench_eer.py dump.sql --from-env
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent
sys.path.insert(0, str(SCRIPT_DIR))

import clean_phpmyadmin_dump as clean  # noqa: E402

CREATE_TABLE_BLOCK_RE = re.compile(
    r"CREATE TABLE `(?P<name>[^`]+)`\s*\((?P<body>[\s\S]*?)\)\s*ENGINE=(?P<engine>[^;]+);",
    re.IGNORECASE,
)
ALTER_TABLE_BLOCK_RE = re.compile(
    r"ALTER TABLE `(?P<name>[^`]+)`\s+(?P<body>[\s\S]*?);",
    re.IGNORECASE,
)
ENV_DB_RE = re.compile(r"^DB_DATABASE=(.+)$", re.MULTILINE)


def read_env_database(project_root: Path) -> str | None:
    env_path = project_root / ".env"
    if not env_path.is_file():
        return None
    match = ENV_DB_RE.search(env_path.read_text(encoding="utf-8", errors="replace"))
    if not match:
        return None
    return match.group(1).strip().strip('"').strip("'")


def resolve_database_name(raw: str, requested: str | None) -> str | None:
    if not requested:
        return read_env_database(PROJECT_ROOT)

    _, blocks = clean.split_into_database_blocks(raw)
    names = [name for name, _ in blocks if name]
    if not names:
        return requested

    if requested in names:
        return requested

    lower = requested.lower()
    for name in names:
        if name.lower() == lower:
            return name

    return requested


def parse_alter_additions(alter_body: str) -> list[str]:
    additions: list[str] = []
    for line in alter_body.splitlines():
        stripped = line.strip().rstrip(",")
        if not stripped or stripped.startswith("--"):
            continue
        upper = stripped.upper()
        if upper.startswith("MODIFY"):
            continue
        if upper.startswith("ADD "):
            clause = stripped[4:].strip()
            if clause.upper().startswith("MODIFY"):
                continue
            additions.append(clause)
    return additions


def collect_alter_clauses(content: str) -> dict[str, list[str]]:
    table_clauses: dict[str, list[str]] = {}
    for match in ALTER_TABLE_BLOCK_RE.finditer(content):
        table = match.group("name")
        for clause in parse_alter_additions(match.group("body")):
            table_clauses.setdefault(table, []).append(clause)
    return table_clauses


def normalize_column_body(body: str) -> str:
    lines = [line.rstrip() for line in body.splitlines()]
    while lines and not lines[0].strip():
        lines.pop(0)
    while lines and not lines[-1].strip():
        lines.pop()
    if not lines:
        return ""
    return "\n".join(lines)


def is_foreign_key_clause(clause: str) -> bool:
    return "FOREIGN KEY" in clause.upper()


def merge_keys_into_create(content: str) -> tuple[str, int, list[str]]:
    alters = collect_alter_clauses(content)
    table_count = 0
    merged_count = 0
    fk_alter_statements: list[str] = []

    def replacer(match: re.Match[str]) -> str:
        nonlocal table_count, merged_count
        table_count += 1
        table = match.group("name")
        body = normalize_column_body(match.group("body"))
        engine = match.group("engine").strip()
        clauses = alters.get(table, [])

        index_clauses = [c for c in clauses if not is_foreign_key_clause(c)]
        fk_clauses = [c for c in clauses if is_foreign_key_clause(c)]

        for fk in fk_clauses:
            if re.search(
                rf"REFERENCES\s+`{re.escape(table)}`\s*\(",
                fk,
                re.IGNORECASE,
            ):
                continue
            fk_alter_statements.append(f"ALTER TABLE `{table}` ADD {fk};")

        if not index_clauses:
            return match.group(0)

        merged_count += 1
        indented_body = body
        if body:
            indented_body = "\n  ".join(line for line in body.splitlines())
        clause_block = ",\n  ".join(index_clauses)
        if indented_body:
            inner = f"  {indented_body},\n  {clause_block}"
        else:
            inner = f"  {clause_block}"
        return f"CREATE TABLE `{table}` (\n{inner}\n) ENGINE={engine};"

    merged = CREATE_TABLE_BLOCK_RE.sub(replacer, content)
    merged = ALTER_TABLE_BLOCK_RE.sub("", merged)
    merged = re.sub(
        r"\n--\s*\n--\s*Индексы сохранённых таблиц[\s\S]*",
        "\n",
        merged,
        flags=re.IGNORECASE,
    )
    merged = re.sub(
        r"\n--\s*\n--\s*AUTO_INCREMENT для сохранённых таблиц[\s\S]*",
        "\n",
        merged,
        flags=re.IGNORECASE,
    )
    merged = re.sub(
        r"\n--\s*\n--\s*Ограничения внешнего ключа[\s\S]*",
        "\n",
        merged,
        flags=re.IGNORECASE,
    )
    merged = re.sub(r"\n{3,}", "\n\n", merged)
    return merged, table_count, fk_alter_statements


def normalize_types_for_workbench(sql: str) -> str:
    """Workbench-парсер не понимает bigint(20) — нужен BIGINT без скобок."""
    sql = re.sub(r"\bbigint\(\d+\)", "BIGINT", sql, flags=re.IGNORECASE)
    sql = re.sub(r"\bint\(\d+\)", "INT", sql, flags=re.IGNORECASE)
    sql = re.sub(r"\btinyint\(\d+\)", "TINYINT", sql, flags=re.IGNORECASE)
    sql = re.sub(r"\bsmallint\(\d+\)", "SMALLINT", sql, flags=re.IGNORECASE)
    sql = re.sub(r"\bmediumint\(\d+\)", "MEDIUMINT", sql, flags=re.IGNORECASE)
    return sql


def fix_create_table_blocks(sql: str) -> str:
    """Убирает пустые строки и лишние запятые перед ')'."""
    pattern = re.compile(
        r"CREATE TABLE `(?P<name>[^`]+)`\s*\((?P<body>[\s\S]*?)\)\s*ENGINE=InnoDB;",
        re.IGNORECASE,
    )

    def fix_block(match: re.Match[str]) -> str:
        lines = [
            line.strip().rstrip(",")
            for line in match.group("body").splitlines()
            if line.strip()
        ]
        cleaned: list[str] = []
        for line in lines:
            upper = line.upper()
            if " FOREIGN KEY " in upper or upper.startswith("CONSTRAINT "):
                continue
            cleaned.append(line)
        body = ",\n  ".join(cleaned)
        return f"CREATE TABLE `{match.group('name')}` (\n  {body}\n) ENGINE=InnoDB;"

    return pattern.sub(fix_block, sql)


def strip_inline_foreign_keys(sql: str) -> str:
    return fix_create_table_blocks(sql)


def simplify_for_workbench(
    sql: str,
    fk_alter_statements: list[str] | None = None,
    *,
    keep_foreign_keys: bool = True,
) -> str:
    """Убирает конструкции, из‑за которых парсер Workbench падает."""
    sql = re.sub(r"^SET .+?;\s*", "", sql, flags=re.MULTILINE | re.IGNORECASE)
    sql = re.sub(
        r"^DROP DATABASE IF EXISTS.+?;\s*",
        "",
        sql,
        flags=re.MULTILINE | re.IGNORECASE,
    )
    sql = re.sub(
        r"^CREATE DATABASE IF NOT EXISTS.+?;\s*",
        "",
        sql,
        flags=re.MULTILINE | re.IGNORECASE,
    )
    sql = re.sub(r"^USE `[^`]+`;\s*", "", sql, flags=re.MULTILINE | re.IGNORECASE)
    sql = re.sub(r"--[^\n]*", "", sql)
    sql = re.sub(r"/\*[\s\S]*?\*/", "", sql)

    # json_valid + CHECK ломают парсер Workbench (вылет на Next).
    sql = re.sub(
        r"\s+CHECK\s*\(\s*json_valid\s*\([^)]+\)\s*\)",
        "",
        sql,
        flags=re.IGNORECASE,
    )
    sql = re.sub(r"\s+CHECK\s*\([^)]+\)", "", sql, flags=re.IGNORECASE)
    sql = re.sub(
        r" CHARACTER SET utf8mb4 COLLATE utf8mb4_bin",
        "",
        sql,
        flags=re.IGNORECASE,
    )
    sql = re.sub(
        r"DEFAULT\s+current_timestamp\(\)\s*ON\s+UPDATE\s+current_timestamp\(\)",
        "DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        sql,
        flags=re.IGNORECASE,
    )
    sql = re.sub(
        r"DEFAULT\s+current_timestamp\(\)",
        "DEFAULT CURRENT_TIMESTAMP",
        sql,
        flags=re.IGNORECASE,
    )
    sql = re.sub(
        r"\)\s*ENGINE=InnoDB\s+DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_\w+;",
        ") ENGINE=InnoDB;",
        sql,
        flags=re.IGNORECASE,
    )

    sql = re.sub(
        r"`schema`",
        "`layout_schema`",
        sql,
        flags=re.IGNORECASE,
    )

    sql = normalize_types_for_workbench(sql)
    sql = strip_inline_foreign_keys(sql)

    fk_section = ""
    if keep_foreign_keys and fk_alter_statements:
        cleaned_fk: list[str] = []
        for stmt in fk_alter_statements:
            stmt = re.sub(
                r"\s+ON DELETE (?:CASCADE|SET NULL|RESTRICT|NO ACTION)",
                "",
                stmt,
                flags=re.IGNORECASE,
            )
            stmt = re.sub(
                r"\s+ON UPDATE (?:CASCADE|SET NULL|RESTRICT|NO ACTION)",
                "",
                stmt,
                flags=re.IGNORECASE,
            )
            cleaned_fk.append(stmt)
        fk_section = "\n\n-- Foreign keys (ALTER — Workbench читает FK отсюда)\n\n"
        fk_section += "\n".join(cleaned_fk) + "\n"

    sql = re.sub(r"[ \t]+\n", "\n", sql)
    sql = re.sub(r"\n{3,}", "\n\n", sql)
    return sql.strip() + fk_section


def build_workbench_sql(
    raw: str,
    database: str | None,
    *,
    keep_foreign_keys: bool = True,
) -> tuple[str, int]:
    db_name = resolve_database_name(raw, database)
    if not db_name:
        raise ValueError("Укажите --database или DB_DATABASE в .env")

    preamble, blocks, _, _ = clean.process_dump(
        raw,
        only_with_tables=False,
        database=db_name,
        schema_only=True,
    )

    if not blocks:
        available = [name for name, _ in clean.split_into_database_blocks(raw)[1] if name]
        hint = ", ".join(available[:12])
        if len(available) > 12:
            hint += ", ..."
        raise ValueError(
            f"База `{db_name}` не найдена или без CREATE TABLE. "
            f"Доступные базы: {hint or 'нет'}"
        )

    _, block = blocks[0]
    if not clean.block_has_tables(block):
        raise ValueError(
            f"База `{db_name}` повреждена в дампе (нет CREATE TABLE). "
            "Выберите другую базу: py scripts/clean_phpmyadmin_dump.py dump.sql --list-databases"
        )

    block = re.sub(
        r"^DROP DATABASE IF EXISTS.*?;\s*",
        "",
        block,
        count=1,
        flags=re.IGNORECASE | re.MULTILINE,
    )
    block = re.sub(
        r"^CREATE DATABASE IF NOT EXISTS.*?;\s*",
        "",
        block,
        flags=re.IGNORECASE | re.MULTILINE,
    )
    block = re.sub(r"^USE `[^`]+`;\s*", "", block, flags=re.IGNORECASE | re.MULTILINE)

    merged, table_count, fk_alters = merge_keys_into_create(block)
    merged = simplify_for_workbench(
        merged,
        fk_alters,
        keep_foreign_keys=keep_foreign_keys,
    )

    fk_count = len(fk_alters) if keep_foreign_keys else 0
    header = (
        "-- MySQL Workbench: CREATE TABLE + ALTER TABLE для внешних ключей\n"
        f"-- База: `{db_name}`\n"
        f"-- Таблиц: {table_count}, внешних ключей: {fk_count}\n\n"
    )

    return header + merged, table_count


def print_workbench_steps(output_path: Path, database: str, table_count: int) -> None:
    print()
    print(f"Готово: {output_path}")
    print(f"База: {database}")
    print(f"Таблиц в файле: {table_count}")
    print()
    print("MySQL Workbench — все таблицы на диаграмму:")
    print("  0. Edit -> Preferences -> Modeling -> Default Target MySQL Version -> 8.0")
    print("  1. File -> New Model")
    print("  2. File -> Import -> Reverse Engineer MySQL Create Script...")
    print(f"  3. Выберите файл: {output_path}")
    print("  4. Next -> Execute -> Finish")
    print("  5. Model -> Create Diagram from Catalog Objects...")
    print("  6. Ctrl+A (все таблицы) -> OK")
    print("  7. Связи (FK) появятся на диаграмме после успешного импорта ALTER TABLE")
    print()
    print("Если снова вылетает на Next — перегенерируйте с --no-foreign-keys")
    print("SQL на сервер выполнять не нужно.")
    print("Не используйте Database -> Reverse Engineer... (с подключением) — он чаще вылетает.")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="SQL для ER-диаграммы MySQL Workbench (все таблицы, стабильный формат).",
    )
    parser.add_argument(
        "input",
        nargs="?",
        type=Path,
        help="SQL-дамп phpMyAdmin (по умолчанию: Downloads/127_0_0_1.sql)",
    )
    parser.add_argument(
        "--database",
        help="Имя базы в дампе (по умолчанию: DB_DATABASE из .env)",
    )
    parser.add_argument(
        "--from-env",
        action="store_true",
        help="Взять имя базы только из .env",
    )
    parser.add_argument(
        "-o",
        "--output",
        type=Path,
        help="Куда сохранить .sql (по умолчанию: Downloads/<база>_workbench_eer.sql)",
    )
    parser.add_argument(
        "--no-foreign-keys",
        action="store_true",
        help="Без FOREIGN KEY (если Workbench всё ещё вылетает на Next)",
    )
    return parser.parse_args()


def default_input_path() -> Path:
    downloads = Path.home() / "Downloads" / "127_0_0_1.sql"
    if downloads.is_file():
        return downloads
    return PROJECT_ROOT / "127_0_0_1.sql"


def default_output_path(database: str) -> Path:
    safe = re.sub(r"[^\w\-]+", "_", database)
    return Path.home() / "Downloads" / f"{safe}_workbench_eer.sql"


def main() -> int:
    args = parse_args()
    input_path = args.input or default_input_path()

    if not input_path.is_file():
        print(f"Файл не найден: {input_path}", file=sys.stderr)
        return 1

    raw = clean.decode_html_entities(
        input_path.read_text(encoding="utf-8", errors="replace")
    )

    database = args.database
    if args.from_env or not database:
        env_db = read_env_database(PROJECT_ROOT)
        if env_db:
            database = env_db

    try:
        sql, table_count = build_workbench_sql(
            raw,
            database,
            keep_foreign_keys=not args.no_foreign_keys,
        )
    except ValueError as exc:
        print(exc, file=sys.stderr)
        return 1

    db_resolved = resolve_database_name(raw, database) or (database or "schema")
    output_path = args.output or default_output_path(db_resolved)
    output_path.write_text(sql, encoding="utf-8")

    print_workbench_steps(output_path, db_resolved, table_count)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
