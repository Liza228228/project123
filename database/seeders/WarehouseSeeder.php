<?php

namespace Database\Seeders;

use App\Models\Subdivision;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use App\Support\AdministrationWarehouse;
use Illuminate\Database\Seeder;

/**
 * Все склады перечислены ниже текстом: подразделение (как в {@see SubdivisionSeeder::definitionNames()}), наименование, тип.
 * Главный склад — подразделение {@see AdministrationWarehouse::SUBDIVISION_NAME}, склад {@see AdministrationWarehouse::WAREHOUSE_NAME}.
 */
class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $defaultType = WarehouseType::query()->firstOrCreate(
            ['name' => 'Оптовый склад'],
            []
        );

        $adminSubdivision = Subdivision::query()->firstWhere('name', AdministrationWarehouse::SUBDIVISION_NAME);
        if ($adminSubdivision !== null) {
            Warehouse::query()->update(['is_primary' => false]);
            Warehouse::query()->updateOrCreate(
                [
                    'subdivision_id' => $adminSubdivision->id,
                    'name' => AdministrationWarehouse::WAREHOUSE_NAME,
                ],
                [
                    'warehouse_type_id' => $defaultType->id,
                    'comment' => null,
                    'is_primary' => true,
                ]
            );
        }

        foreach ($this->warehouseRows() as $row) {
            $subdivision = Subdivision::query()->firstWhere('name', $row['subdivision']);
            if ($subdivision === null) {
                $this->command?->warn("Пропуск склада «{$row['name']}»: нет подразделения «{$row['subdivision']}».");

                continue;
            }

            $typeName = $row['type'] !== '' ? $row['type'] : 'Оптовый склад';
            $warehouseTypeId = $typeName === 'Оптовый склад'
                ? $defaultType->id
                : WarehouseType::query()->firstOrCreate(['name' => $typeName], [])->id;

            Warehouse::query()->updateOrCreate(
                [
                    'subdivision_id' => $subdivision->id,
                    'name' => $row['name'],
                ],
                [
                    'warehouse_type_id' => $warehouseTypeId,
                    'comment' => $row['comment'] !== null && $row['comment'] !== '' ? $row['comment'] : null,
                    'is_primary' => false,
                ]
            );
        }
    }

    /**
     * @return list<array{subdivision: string, name: string, type: string, comment: ?string}>
     */
    private function warehouseRows(): array
    {
        return [
            ['subdivision' => 'Район тепловых сетей Северный', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Район тепловых сетей Южный', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Район тепловых сетей Южный', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Район тепловых сетей Центральный', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Район тепловых сетей Центральный', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Район тепловых сетей Центральный', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Район тепловых сетей Восточный', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Район тепловых сетей Восточный', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Район тепловых сетей Восточный', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Район тепловых сетей Восточный', 'name' => 'Склад №4', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Ремонтно-механический участок', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Лаборатория технического контроля', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Лаборатория технического контроля', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Киренск правый берег', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Киренск правый берег', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Киренск правый берег', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Киренск левый берег', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Киренск левый берег', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Киренск левый берег', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Киренск левый берег', 'name' => 'Склад №4', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма больница', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма Инвест котлы СЭЛ', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма Инвест котлы СЭЛ', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма Инвест КТР', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма Инвест КТР', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма Инвест КТР', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма Инвест т/с Киевский', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма Инвест т/с Киевский', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма Инвест т/с Киевский', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма Инвест т/с Киевский', 'name' => 'Склад №4', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КАП', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КАПЫ из тарифа', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КАПЫ из тарифа', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР ГВС и ЦТП', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР ГВС и ЦТП', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР ГВС и ЦТП', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР кот Киевская', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР кот Киевская', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР кот Киевская', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР кот Киевская', 'name' => 'Склад №4', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР кот СЭЛ', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР т/с Киевская', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР т/с Киевская', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР т/с СЭЛ', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР т/с СЭЛ', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР т/с СЭЛ', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР т/с Химки', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР т/с Химки', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР т/с Химки', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР т/с Химки', 'name' => 'Склад №4', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР школа', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма ЦТП Киевский', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма ЦТП Киевский', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'ИГИРМА теплотрасса', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ИГИРМА теплотрасса', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ИГИРМА теплотрасса', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'ИНК', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ИНК', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ИНК', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ИНК', 'name' => 'Склад №4', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Ручей', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Усть-Кут', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Усть-Кут', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'ЯНТАЛЬ', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ЯНТАЛЬ', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ЯНТАЛЬ', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
        ];
    }
}
