<?php

// начальные данные для базы
namespace Database\Seeders;

use App\Models\Subdivision;
use App\Support\AdministrationWarehouse;
use Illuminate\Database\Seeder;
class SubdivisionSeeder extends Seeder
{
    public static function definitionNames(): array
    {
        return [
            AdministrationWarehouse::SUBDIVISION_NAME,
            'Район тепловых сетей Северный',
            'Район тепловых сетей Южный',
            'Район тепловых сетей Центральный',
            'Район тепловых сетей Восточный',
            'Ремонтно-механический участок',
            'Лаборатория технического контроля',
            'Киренск правый берег',
            'Киренск левый берег',
            'Игирма больница',
            'Игирма Инвест котлы СЭЛ',
            'Игирма Инвест КТР',
            'Игирма Инвест т/с Киевский',
            'Игирма КАП',
            'Игирма КАПЫ из тарифа',
            'Игирма КР ГВС и ЦТП',
            'Игирма КР кот Киевская',
            'Игирма КР кот СЭЛ',
            'Игирма КР т/с Киевская',
            'Игирма КР т/с СЭЛ',
            'Игирма КР т/с Химки',
            'Игирма КР школа',
            'Игирма ЦТП Киевский',
            'ИГИРМА теплотрасса',
            'ИНК',
            'Ручей',
            'Усть-Кут',
            'ЯНТАЛЬ',
        ];
    }

    public function run(): void
    {
        foreach (self::definitionNames() as $name) {
            Subdivision::query()->firstOrCreate(['name' => $name]);
        }
    }
}
