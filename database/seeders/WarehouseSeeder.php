<?php

namespace Database\Seeders;

use App\Models\Subdivision;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Illuminate\Database\Seeder;

/**
 * Все склады перечислены ниже текстом: подразделение (как в {@see SubdivisionSeeder::definitionNames()}), код, наименование, тип.
 * У «ЯНТАЛЬ» первый пункт — «Администрация офис» (для {@see AdministrationPrimaryWarehouseSeeder}).
 */
class WarehouseSeeder extends Seeder
{
    /**
     * Коды оборудования со старых импортов — удаляются при сиде.
     *
     * @var list<string>
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
        $defaultType = WarehouseType::query()->firstOrCreate(
            ['name' => 'Оптовый склад'],
            []
        );

        Warehouse::query()
            ->whereIn('code', self::EXCLUDED_WAREHOUSE_CODES)
            ->delete();

        foreach ($this->warehouseRows() as $row) {
            if (in_array($row['code'], self::EXCLUDED_WAREHOUSE_CODES, true)) {
                continue;
            }

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
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'subdivision_id' => $subdivision->id,
                    'warehouse_type_id' => $warehouseTypeId,
                    'comment' => $row['comment'] !== null && $row['comment'] !== '' ? $row['comment'] : null,
                    'is_primary' => false,
                ]
            );
        }
    }

    /**
     * @return list<array{subdivision: string, code: string, name: string, type: string, comment: ?string}>
     */
    private function warehouseRows(): array
    {
        return [
            ['subdivision' => 'Район тепловых сетей Северный', 'code' => 'W000000001', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Район тепловых сетей Южный', 'code' => 'W000000002', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Район тепловых сетей Южный', 'code' => 'W000000003', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Район тепловых сетей Центральный', 'code' => 'W000000004', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Район тепловых сетей Центральный', 'code' => 'W000000005', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Район тепловых сетей Центральный', 'code' => 'W000000006', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Район тепловых сетей Восточный', 'code' => 'W000000007', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Район тепловых сетей Восточный', 'code' => 'W000000008', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Район тепловых сетей Восточный', 'code' => 'W000000009', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Район тепловых сетей Восточный', 'code' => 'W000000010', 'name' => 'Склад №4', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Ремонтно-механический участок', 'code' => 'W000000011', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Лаборатория технического контроля', 'code' => 'W000000012', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Лаборатория технического контроля', 'code' => 'W000000013', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Киренск правый берег', 'code' => 'W000000014', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Киренск правый берег', 'code' => 'W000000015', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Киренск правый берег', 'code' => 'W000000016', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Киренск левый берег', 'code' => 'W000000017', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Киренск левый берег', 'code' => 'W000000018', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Киренск левый берег', 'code' => 'W000000019', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Киренск левый берег', 'code' => 'W000000020', 'name' => 'Склад №4', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма больница', 'code' => 'W000000021', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма Инвест котлы СЭЛ', 'code' => 'W000000022', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма Инвест котлы СЭЛ', 'code' => 'W000000023', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма Инвест КТР', 'code' => 'W000000024', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма Инвест КТР', 'code' => 'W000000025', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма Инвест КТР', 'code' => 'W000000026', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма Инвест т/с Киевский', 'code' => 'W000000027', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма Инвест т/с Киевский', 'code' => 'W000000028', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма Инвест т/с Киевский', 'code' => 'W000000029', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма Инвест т/с Киевский', 'code' => 'W000000030', 'name' => 'Склад №4', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КАП', 'code' => 'W000000031', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КАПЫ из тарифа', 'code' => 'W000000032', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КАПЫ из тарифа', 'code' => 'W000000033', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР ГВС и ЦТП', 'code' => 'W000000034', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР ГВС и ЦТП', 'code' => 'W000000035', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР ГВС и ЦТП', 'code' => 'W000000036', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР кот Киевская', 'code' => 'W000000037', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР кот Киевская', 'code' => 'W000000038', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР кот Киевская', 'code' => 'W000000039', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР кот Киевская', 'code' => 'W000000040', 'name' => 'Склад №4', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР кот СЭЛ', 'code' => 'W000000041', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР т/с Киевская', 'code' => 'W000000042', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР т/с Киевская', 'code' => 'W000000043', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР т/с СЭЛ', 'code' => 'W000000044', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР т/с СЭЛ', 'code' => 'W000000045', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР т/с СЭЛ', 'code' => 'W000000046', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР т/с Химки', 'code' => 'W000000047', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР т/с Химки', 'code' => 'W000000048', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР т/с Химки', 'code' => 'W000000049', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма КР т/с Химки', 'code' => 'W000000050', 'name' => 'Склад №4', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма КР школа', 'code' => 'W000000051', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Игирма ЦТП Киевский', 'code' => 'W000000052', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Игирма ЦТП Киевский', 'code' => 'W000000053', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'ИГИРМА теплотрасса', 'code' => 'W000000054', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ИГИРМА теплотрасса', 'code' => 'W000000055', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ИГИРМА теплотрасса', 'code' => 'W000000056', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'ИНК', 'code' => 'W000000057', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ИНК', 'code' => 'W000000058', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ИНК', 'code' => 'W000000059', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ИНК', 'code' => 'W000000060', 'name' => 'Склад №4', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Ручей', 'code' => 'W000000061', 'name' => 'Склад', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'Усть-Кут', 'code' => 'W000000062', 'name' => 'Склад №1', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'Усть-Кут', 'code' => 'W000000063', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],

            ['subdivision' => 'ЯНТАЛЬ', 'code' => 'W000000064', 'name' => 'Администрация офис', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ЯНТАЛЬ', 'code' => 'W000000065', 'name' => 'Склад №2', 'type' => 'Оптовый склад', 'comment' => null],
            ['subdivision' => 'ЯНТАЛЬ', 'code' => 'W000000066', 'name' => 'Склад №3', 'type' => 'Оптовый склад', 'comment' => null],
        ];
    }
}
