<?php


namespace App\Console\Commands;

use App\Models\Subdivision;
use App\Models\Warehouse;
use Illuminate\Console\Command;

class ImportWarehouseDataFromDataFolder extends Command
{
    protected $signature = 'warehouse:import-data {--path= : Каталог с JSON-файлами (по умолчанию: Data в корне проекта)}';

    protected $description = <<<'TXT'
Импорт иерархии из JSON: подразделения → склады подразделения.
Формат файла *.json:
{
  "subdivisions": [
    {
      "name": "Подразделение",
      "warehouses": [
        { "name": "Склад 1", "code": "W-001", "is_primary": false }
      ]
    }
  ]
}
Корневые поля name/code в JSON опциональны (для подписи в логе).
Поля warehouse: name, is_primary (опционально), warehouse_type_id, comment — опционально.
TXT;

    public function handle(): int
    {
        $dir = $this->option('path') ?: base_path('Data');

        if (! is_dir($dir)) {
            $this->error('Каталог не найден: '.$dir);

            return self::FAILURE;
        }

        $pattern = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.json';
        $files = glob($pattern) ?: [];

        if ($files === []) {
            $this->warn('JSON-файлы не найдены по шаблону: '.$pattern);

            return self::SUCCESS;
        }

        foreach ($files as $path) {
            $this->importFile($path);
        }

        return self::SUCCESS;
    }

    private function importFile(string $path): void
    {
        $basename = basename($path);
        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->error('Не удалось прочитать: '.$basename);

            return;
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            $this->error('Некорректный JSON: '.$basename);

            return;
        }

        $label = trim((string) ($data['name'] ?? ''));
        if ($label === '') {
            $label = pathinfo($basename, PATHINFO_FILENAME);
        }

        $subdivisions = $data['subdivisions'] ?? null;
        if (! is_array($subdivisions) || $subdivisions === []) {
            $this->warn('Пропуск (нет subdivisions): '.$basename);

            return;
        }

        $this->info('Файл: '.$basename.' ('.$label.')');

        foreach ($subdivisions as $subRow) {
            if (! is_array($subRow)) {
                continue;
            }
            $subName = trim((string) ($subRow['name'] ?? ''));
            if ($subName === '') {
                continue;
            }

            $subdivision = Subdivision::query()->updateOrCreate(
                ['name' => $subName],
                []
            );

            $warehouses = $subRow['warehouses'] ?? [];
            if (! is_array($warehouses)) {
                continue;
            }

            foreach ($warehouses as $idx => $whRow) {
                if (! is_array($whRow)) {
                    continue;
                }
                $whName = trim((string) ($whRow['name'] ?? ''));
                if ($whName === '') {
                    continue;
                }

                Warehouse::query()->updateOrCreate(
                    [
                        'subdivision_id' => $subdivision->id,
                        'name' => $whName,
                    ],
                    [
                        'is_primary' => (bool) ($whRow['is_primary'] ?? false),
                        'warehouse_type_id' => $whRow['warehouse_type_id'] ?? null,
                        'comment' => isset($whRow['comment']) ? (string) $whRow['comment'] : null,
                    ]
                );
            }
        }
    }
}
