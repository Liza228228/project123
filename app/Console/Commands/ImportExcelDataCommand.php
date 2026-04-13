<?php

namespace App\Console\Commands;

use App\Models\Warehouse;
use App\Models\WarehouseType;
use Illuminate\Console\Command;

class ImportExcelDataCommand extends Command
{
    protected $signature = 'import:excel-data 
                            {file=2.csv : Путь к CSV (2.csv) или all}
                            {--encoding= : Кодировка файла (utf-8 или windows-1251)}';

    protected $description = 'Импорт данных из Data/2.csv (склады)';

    public function handle(): int
    {
        $file = $this->argument('file');
        $encoding = $this->option('encoding') ?: 'windows-1251';
        $basePath = base_path('Data');

        if ($file === 'all') {
            $this->importFile($basePath.DIRECTORY_SEPARATOR.'2.csv', 'warehouses', $encoding);

            return self::SUCCESS;
        }

        $path = $basePath.DIRECTORY_SEPARATOR.$file;
        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        if (! str_contains($file, '2.')) {
            $this->error('Поддерживается только импорт складов из 2.csv.');

            return self::FAILURE;
        }

        $this->importFile($path, 'warehouses', $encoding);

        return self::SUCCESS;
    }

    private function importFile(string $path, string $type, string $encoding): void
    {
        $content = file_get_contents($path);
        if ($encoding === 'windows-1251') {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1251');
        }
        $lines = array_filter(explode("\n", $content), fn ($l) => trim($l) !== '');
        if (empty($lines)) {
            $this->warn("Файл пуст: {$path}");

            return;
        }

        $header = str_getcsv(array_shift($lines), ';');
        $header = array_map('trim', $header);

        $this->importWarehouses($lines, $header);
    }

    private function importWarehouses(array $lines, array $header): void
    {
        $this->info('Импорт складов (2.csv)...');
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($lines as $line) {
            $row = str_getcsv($line, ';');
            if (count($row) < 3) {
                $skipped++;

                continue;
            }
            $row = array_pad($row, count($header), null);
            $data = array_combine($header, $row);
            if (! is_array($data)) {
                $skipped++;

                continue;
            }

            $isPrimary = $this->normalizeBool($data['Основной'] ?? $data['Îñíîâíîé'] ?? 'Нет');
            $name = trim($data['Наименование'] ?? $data['Íàèìåíîâàíèå'] ?? '');
            $code = trim($data['Код'] ?? $data['Êîä'] ?? '');
            $warehouseTypeName = trim($data['Тип склада'] ?? $data['Òèï ñêëàäà'] ?? '');
            $comment = trim($data['Комментарий'] ?? $data['Êîììåíòàðèé'] ?? '');

            if ($name === '' || $code === '') {
                $skipped++;

                continue;
            }

            $warehouseTypeId = null;
            if ($warehouseTypeName !== '') {
                $warehouseTypeId = WarehouseType::firstOrCreate(
                    ['name' => $warehouseTypeName],
                    ['name' => $warehouseTypeName]
                )->id;
            }
            $warehouse = Warehouse::firstOrNew(['code' => $code]);
            $warehouse->is_primary = $isPrimary;
            $warehouse->name = $name;
            $warehouse->warehouse_type_id = $warehouseTypeId;
            $warehouse->comment = $comment !== '' ? $comment : null;
            if ($warehouse->exists) {
                $warehouse->save();
                $updated++;
            } else {
                $warehouse->save();
                $created++;
            }
        }

        $this->info("Склады: создано {$created}, обновлено {$updated}, пропущено {$skipped}.");
    }

    private function normalizeBool(string $value): bool
    {
        $v = mb_strtolower(trim($value));

        return $v === 'да' || $v === 'yes' || $v === '1' || $v === 'true';
    }
}
