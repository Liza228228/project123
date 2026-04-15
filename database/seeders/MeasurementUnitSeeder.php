<?php

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
            ['type_code' => 'piece', 'code' => 'шт', 'name' => 'Штука', 'is_base' => true, 'multiplier_to_base' => 1],
            ['type_code' => 'mass', 'code' => 'г', 'name' => 'Грамм', 'is_base' => false, 'multiplier_to_base' => 0.001],
            ['type_code' => 'mass', 'code' => 'кг', 'name' => 'Килограмм', 'is_base' => true, 'multiplier_to_base' => 1],
            ['type_code' => 'mass', 'code' => 'т', 'name' => 'Тонна', 'is_base' => false, 'multiplier_to_base' => 1000],
            ['type_code' => 'length', 'code' => 'мм', 'name' => 'Миллиметр', 'is_base' => true, 'multiplier_to_base' => 1],
            ['type_code' => 'length', 'code' => 'см', 'name' => 'Сантиметр', 'is_base' => false, 'multiplier_to_base' => 10],
            ['type_code' => 'length', 'code' => 'м', 'name' => 'Метр', 'is_base' => false, 'multiplier_to_base' => 1000],
            ['type_code' => 'length', 'code' => 'км', 'name' => 'Километр', 'is_base' => false, 'multiplier_to_base' => 1000000],
            ['type_code' => 'clothing_size', 'code' => 'разм', 'name' => 'Размер', 'is_base' => true, 'multiplier_to_base' => 1],
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
                    'is_base' => (bool) $unit['is_base'],
                    'multiplier_to_base' => (float) $unit['multiplier_to_base'],
                ]
            );
        }
    }
}
