<?php

// начальные данные для базы
namespace Database\Seeders;

use App\Models\MeasurementUnit;
use App\Models\UnitType;
use Illuminate\Database\Seeder;

class MeasurementUnitSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'piece', 'name' => 'Штучные'],
            ['code' => 'mass', 'name' => 'Масса'],
            ['code' => 'length', 'name' => 'Длина'],
            ['code' => 'clothing_size', 'name' => 'Размер одежды'],
        ];

        $typeIds = [];
        foreach ($types as $type) {
            $row = UnitType::query()->updateOrCreate(
                ['code' => $type['code']],
                ['name' => $type['name']]
            );
            $typeIds[$type['code']] = (int) $row->id;
        }

        $units = [
            ['type_code' => 'piece', 'code' => 'шт', 'name' => 'Штука'],
            ['type_code' => 'mass', 'code' => 'г', 'name' => 'Грамм'],
            ['type_code' => 'mass', 'code' => 'кг', 'name' => 'Килограмм'],
            ['type_code' => 'mass', 'code' => 'т', 'name' => 'Тонна'],
            ['type_code' => 'length', 'code' => 'мм', 'name' => 'Миллиметр'],
            ['type_code' => 'length', 'code' => 'см', 'name' => 'Сантиметр'],
            ['type_code' => 'length', 'code' => 'м', 'name' => 'Метр'],
            ['type_code' => 'length', 'code' => 'км', 'name' => 'Километр'],
            ['type_code' => 'clothing_size', 'code' => 'разм', 'name' => 'Размер'],
        ];

        foreach ($units as $unit) {
            $typeCode = $unit['type_code'];
            if (! isset($typeIds[$typeCode])) {
                continue;
            }

            MeasurementUnit::query()->updateOrCreate(
                [
                    'unit_type_id' => $typeIds[$typeCode],
                    'code' => $unit['code'],
                ],
                [
                    'name' => $unit['name'],
                ]
            );
        }
    }
}
