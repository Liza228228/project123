<?php

namespace Database\Seeders;

use App\Models\Subdivision;
use Illuminate\Database\Seeder;

class SubdivisionSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Район тепловых сетей Северный',
            'Район тепловых сетей Южный',
            'Район тепловых сетей Центральный',
            'Район тепловых сетей Восточный',
            'Участок котельных',
            'Участок ЦТП (центральные тепловые пункты)',
            'Служба подземных коммуникаций',
            'Ремонтно-механический участок',
            'Аварийно-диспетчерская служба',
            'Служба эксплуатации тепловых сетей',
            'Лаборатория технического контроля',
            'Склад материалов и оборудования',
        ];

        foreach ($names as $name) {
            Subdivision::firstOrCreate(['name' => $name]);
        }
    }
}
