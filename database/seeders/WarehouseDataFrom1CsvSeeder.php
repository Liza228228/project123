<?php

namespace Database\Seeders;

use App\Models\Subdivision;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Illuminate\Database\Seeder;

/**
 * Заполняет подразделения и склады из встроенного снимка (database/seeders/data/warehouse_1_csv.php).
 * Логика CSV: строка «Да» — подразделение (группа); все следующие строки «Нет» до следующей «Да» — склады этой группы
 * (связь warehouses.subdivision_id → subdivisions.id). Подряд несколько «Да» без «Нет» между ними: склады ниже
 * относятся к последнему «Да» (внутренняя «папка»). Колонка «Это группа» в БД не хранится.
 */
class WarehouseDataFrom1CsvSeeder extends Seeder
{
    public function run(): void
    {
        $dataPath = __DIR__.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'warehouse_1_csv.php';
        if (! is_readable($dataPath)) {
            $this->command?->error("Нет встроенных данных: {$dataPath}");

            return;
        }

        /** @var string $raw */
        $raw = require $dataPath;
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $lines = preg_split('/\r\n|\r|\n/', $raw);

        $defaultType = WarehouseType::query()->firstOrCreate(
            ['name' => 'Оптовый склад'],
            []
        );

        $currentSubdivision = null;

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($index === 0 && str_contains($line, 'Это группа')) {
                continue;
            }

            $row = str_getcsv($line, ';');
            $flag = mb_strtolower(trim($row[0] ?? ''));
            $name = trim($row[1] ?? '');
            $code = trim($row[2] ?? '');
            $typeName = trim($row[3] ?? '');
            $comment = trim($row[4] ?? '');

            if ($name === '') {
                continue;
            }

            if ($flag === 'да') {
                $currentSubdivision = Subdivision::query()->updateOrCreate(
                    ['name' => $name],
                    []
                );

                continue;
            }

            if ($flag !== 'нет') {
                continue;
            }

            if ($currentSubdivision === null) {
                $this->command?->warn("Строка «Нет» без предшествующего «Да»: {$name}");

                continue;
            }

            if ($code === '') {
                $this->command?->warn("Пропуск склада без кода: {$name}");

                continue;
            }

            $warehouseTypeId = $defaultType->id;
            if ($typeName !== '') {
                $warehouseTypeId = WarehouseType::query()->firstOrCreate(
                    ['name' => $typeName],
                    []
                )->id;
            }

            Warehouse::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'subdivision_id' => $currentSubdivision->id,
                    'warehouse_type_id' => $warehouseTypeId,
                    'retail_price_type_id' => null,
                    'comment' => $comment !== '' ? $comment : null,
                    'is_primary' => false,
                ]
            );
        }
    }
}
