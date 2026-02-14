<?php

namespace Database\Seeders;

use App\Models\Subdivision;
use Illuminate\Database\Seeder;

class SubdivisionSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Участок №1',
            'Участок №2',
            'Участок №3',
            'Склад',
            'Ремонтная служба',
        ];

        foreach ($names as $name) {
            Subdivision::firstOrCreate(['name' => $name]);
        }
    }
}
