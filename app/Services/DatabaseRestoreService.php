<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseRestoreService
{
    /**
     * @return array{file_name:string,storage_path:string}
     */
    public function createSqlBackup(): array
    {
        $defaultConnectionName = (string) Config::get('database.default');
        $connectionConfig = (array) Config::get("database.connections.{$defaultConnectionName}", []);
        $driver = (string) ($connectionConfig['driver'] ?? '');
        $timestamp = now()->format('Ymd_His');
        $fileName = "backup_{$driver}_{$timestamp}.sql";
        $storagePath = 'db-backups/'.$fileName;
        $absoluteOutputPath = storage_path('app'.DIRECTORY_SEPARATOR.$storagePath);
        $this->ensureDirectory(dirname($absoluteOutputPath));

        if ($driver === 'sqlite') {
            $this->backupSqlite($absoluteOutputPath, $connectionConfig);

            return ['file_name' => $fileName, 'storage_path' => $storagePath];
        }

        if ($driver === 'mysql') {
            $this->backupMysql($absoluteOutputPath, $connectionConfig);

            return ['file_name' => $fileName, 'storage_path' => $storagePath];
        }

        if ($driver === 'pgsql') {
            $this->backupPgsql($absoluteOutputPath, $connectionConfig);

            return ['file_name' => $fileName, 'storage_path' => $storagePath];
        }

        throw new RuntimeException("Создание backup не поддерживается для драйвера: {$driver}");
    }

    public function restoreFromSqlFile(string $absoluteSqlPath): void
    {
        if (! is_file($absoluteSqlPath)) {
            throw new RuntimeException('Файл дампа не найден.');
        }

        $defaultConnectionName = (string) Config::get('database.default');
        $connectionConfig = (array) Config::get("database.connections.{$defaultConnectionName}", []);
        $driver = (string) ($connectionConfig['driver'] ?? '');

        if ($driver === 'sqlite') {
            $this->restoreSqlite($absoluteSqlPath, $connectionConfig);

            return;
        }

        if ($driver === 'mysql') {
            $this->restoreMysql($absoluteSqlPath, $connectionConfig);

            return;
        }

        if ($driver === 'pgsql') {
            $this->restorePgsql($absoluteSqlPath, $connectionConfig);

            return;
        }

        throw new RuntimeException("Восстановление не поддерживается для драйвера: {$driver}");
    }

    /**
     * @param array<string, mixed> $connectionConfig
     */
    private function restoreSqlite(string $absoluteSqlPath, array $connectionConfig): void
    {
        $databasePath = $this->resolveSqliteDatabasePath($connectionConfig);
        if ($databasePath === '') {
            throw new RuntimeException('Не задан путь к SQLite базе.');
        }

        DB::purge();
        $pdo = DB::connection()->getPdo();
        $sql = (string) file_get_contents($absoluteSqlPath);
        $pdo->exec($sql);
    }

    /**
     * @param array<string, mixed> $connectionConfig
     */
    private function restoreMysql(string $absoluteSqlPath, array $connectionConfig): void
    {
        $host = (string) ($connectionConfig['host'] ?? '127.0.0.1');
        $port = (string) ($connectionConfig['port'] ?? '3306');
        $database = (string) ($connectionConfig['database'] ?? '');
        $username = (string) ($connectionConfig['username'] ?? '');
        $password = (string) ($connectionConfig['password'] ?? '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('Недостаточно параметров подключения MySQL.');
        }

        $mysqlBinary = $this->resolveBinaryPath(
            ['DB_MYSQL_BINARY', 'MYSQL_BINARY'],
            ['mysql.exe', 'mysql'],
            [
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysql.exe',
                'C:\\xampp\\mysql\\bin\\mysql.exe',
                'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysql.exe',
            ]
        );

        $process = new Process([
            $mysqlBinary,
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            '--default-character-set=utf8mb4',
            $database,
        ]);

        $env = [];
        if ($password !== '') {
            $env['MYSQL_PWD'] = $password;
        }

        $process->setEnv($env);
        $process->setTimeout(300);
        $process->setInput((string) file_get_contents($absoluteSqlPath));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Ошибка восстановления MySQL: '.$process->getErrorOutput());
        }
    }

    /**
     * @param array<string, mixed> $connectionConfig
     */
    private function restorePgsql(string $absoluteSqlPath, array $connectionConfig): void
    {
        $host = (string) ($connectionConfig['host'] ?? '127.0.0.1');
        $port = (string) ($connectionConfig['port'] ?? '5432');
        $database = (string) ($connectionConfig['database'] ?? '');
        $username = (string) ($connectionConfig['username'] ?? '');
        $password = (string) ($connectionConfig['password'] ?? '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('Недостаточно параметров подключения PostgreSQL.');
        }

        $process = new Process([
            'psql',
            '--host='.$host,
            '--port='.$port,
            '--username='.$username,
            '--dbname='.$database,
            '--file='.$absoluteSqlPath,
        ]);

        $env = [];
        if ($password !== '') {
            $env['PGPASSWORD'] = $password;
        }

        $process->setEnv($env);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Ошибка восстановления PostgreSQL: '.$process->getErrorOutput());
        }
    }

    /**
     * @param array<string, mixed> $connectionConfig
     */
    private function backupSqlite(string $absoluteOutputPath, array $connectionConfig): void
    {
        $databasePath = $this->resolveSqliteDatabasePath($connectionConfig);
        if ($databasePath === '') {
            throw new RuntimeException('Не задан путь к SQLite базе.');
        }

        $process = new Process([
            'sqlite3',
            $databasePath,
            '.dump',
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Ошибка создания backup SQLite: '.$process->getErrorOutput());
        }

        file_put_contents($absoluteOutputPath, $process->getOutput());
    }

    /**
     * @param array<string, mixed> $connectionConfig
     */
    private function backupMysql(string $absoluteOutputPath, array $connectionConfig): void
    {
        $host = (string) ($connectionConfig['host'] ?? '127.0.0.1');
        $port = (string) ($connectionConfig['port'] ?? '3306');
        $database = (string) ($connectionConfig['database'] ?? '');
        $username = (string) ($connectionConfig['username'] ?? '');
        $password = (string) ($connectionConfig['password'] ?? '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('Недостаточно параметров подключения MySQL.');
        }

        $mysqldumpBinary = $this->resolveBinaryPath(
            ['DB_MYSQLDUMP_BINARY', 'MYSQLDUMP_BINARY'],
            ['mysqldump.exe', 'mysqldump'],
            [
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
            ]
        );

        $baseArgs = [
            $mysqldumpBinary,
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            '--single-transaction',
            '--quick',
        ];

        $env = [];
        if ($password !== '') {
            $env['MYSQL_PWD'] = $password;
        }

        $result = $this->runMysqlDumpWithArgs([
            ...$baseArgs,
            '--set-gtid-purged=OFF',
            $database,
        ], $env);

        if (! $result['ok']) {
            $error = $result['error'];
            $unknownGtidOption = str_contains($error, 'unknown variable \'set-gtid-purged=OFF\'')
                || str_contains($error, 'unknown option \'--set-gtid-purged=OFF\'');

            if ($unknownGtidOption) {
                // Для некоторых сборок (например, MariaDB/XAMPP) флаг GTID не поддерживается.
                $result = $this->runMysqlDumpWithArgs([
                    ...$baseArgs,
                    $database,
                ], $env);
            }
        }

        if (! $result['ok']) {
            $socketIssue = str_contains($result['error'], 'Got error: 2004')
                && str_contains($result['error'], 'Can\'t create TCP/IP socket');
            if ($socketIssue) {
                // Fallback для Windows/XAMPP: пробуем localhost без порта.
                $result = $this->runMysqlDumpWithArgs([
                    $mysqldumpBinary,
                    '--host=localhost',
                    '--user='.$username,
                    '--single-transaction',
                    '--quick',
                    $database,
                ], $env);
            }
        }

        if (! $result['ok']) {
            throw new RuntimeException('Ошибка создания backup MySQL: '.$result['error']);
        }

        file_put_contents($absoluteOutputPath, $result['output']);
    }

    /**
     * @param array<string, mixed> $connectionConfig
     */
    private function backupPgsql(string $absoluteOutputPath, array $connectionConfig): void
    {
        $host = (string) ($connectionConfig['host'] ?? '127.0.0.1');
        $port = (string) ($connectionConfig['port'] ?? '5432');
        $database = (string) ($connectionConfig['database'] ?? '');
        $username = (string) ($connectionConfig['username'] ?? '');
        $password = (string) ($connectionConfig['password'] ?? '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('Недостаточно параметров подключения PostgreSQL.');
        }

        $process = new Process([
            'pg_dump',
            '--host='.$host,
            '--port='.$port,
            '--username='.$username,
            '--dbname='.$database,
            '--format=plain',
            '--file='.$absoluteOutputPath,
        ]);
        $env = [];
        if ($password !== '') {
            $env['PGPASSWORD'] = $password;
        }
        $process->setEnv($env);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Ошибка создания backup PostgreSQL: '.$process->getErrorOutput());
        }
    }

    /**
     * @param array<string, mixed> $connectionConfig
     */
    private function resolveSqliteDatabasePath(array $connectionConfig): string
    {
        $databasePath = (string) ($connectionConfig['database'] ?? '');
        if ($databasePath === '') {
            return '';
        }

        if (
            str_starts_with($databasePath, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $databasePath) === 1
        ) {
            return $databasePath;
        }

        return database_path($databasePath);
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Не удалось создать директорию для backup.');
        }
    }

    /**
     * @param list<string> $envKeys
     * @param list<string> $defaultCandidates
     * @param list<string> $windowsCandidates
     */
    private function resolveBinaryPath(array $envKeys, array $defaultCandidates, array $windowsCandidates = []): string
    {
        foreach ($envKeys as $envKey) {
            $configured = trim((string) env($envKey, ''));
            if ($configured !== '') {
                return $configured;
            }
        }

        foreach ($defaultCandidates as $candidate) {
            if ($this->canExecuteBinary($candidate)) {
                return $candidate;
            }
        }

        foreach ($windowsCandidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $hint = implode(', ', $envKeys);
        throw new RuntimeException("Не найден бинарник СУБД. Укажите путь в .env через: {$hint}");
    }

    private function canExecuteBinary(string $binary): bool
    {
        $probe = new Process([$binary, '--version']);
        $probe->setTimeout(5);
        $probe->run();

        return $probe->isSuccessful();
    }

    /**
     * @param list<string> $args
     * @param array<string, string> $env
     * @return array{ok:bool,output:string,error:string}
     */
    private function runMysqlDumpWithArgs(array $args, array $env): array
    {
        $process = new Process($args);
        $process->setEnv($env);
        $process->setTimeout(300);
        $process->run();

        return [
            'ok' => $process->isSuccessful(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ];
    }
}
