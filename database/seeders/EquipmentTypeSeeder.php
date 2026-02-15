<?php

namespace Database\Seeders;

use App\Models\EquipmentType;
use Illuminate\Database\Seeder;

class EquipmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Труба',
            'Уровень',
            'Рулетка',
        ];

        foreach ($names as $name) {
            EquipmentType::firstOrCreate(['name' => $name]);
        }
    }
}
