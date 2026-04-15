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
    /**
     * Коды, которые должны учитываться как оборудование, а не как склады.
     *
     * @var array<int, string>
     */
    private const EXCLUDED_WAREHOUSE_CODES = [
        'БП-000150',
        '00-000061',
        '00-000059',
        '00-000060',
        'БП-000147',
        '00-000082',
        'БП-000144',
        'БП-000151',
        '00-000111',
        'БП-000155',
        'БП-000142',
        'БП-000163',
        '00-000112',
        'БП-000164',
        'БП-000146',
        'БП-000140',
        'БП-000154',
        '00-000132',
        'БП-000145',
        'БП-000162',
        'БП-000153',
        'БП-000139',
        'БП-000143',
        '00-000120',
        '00-000131',
    ];

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

        // Удаляем ранее импортированные записи, которые должны быть оборудованием.
        Warehouse::query()
            ->whereIn('code', self::EXCLUDED_WAREHOUSE_CODES)
            ->delete();

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

            if (in_array($code, self::EXCLUDED_WAREHOUSE_CODES, true)) {
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
                    'comment' => $comment !== '' ? $comment : null,
                    'is_primary' => false,
                ]
            );
        }
    }
}
