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
                array_merge(
                    [
                        'warehouse_type_id' => $defaultType->id,
                        'comment' => null,
                        'is_primary' => true,
                    ],
                    self::subdivisionAddressCatalog()[AdministrationWarehouse::SUBDIVISION_NAME] ?? [],
                    ['address_house' => '15']
                )
            );
        }

        $houseCounters = [];

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

            $subdivisionName = $row['subdivision'];
            $houseCounters[$subdivisionName] = ($houseCounters[$subdivisionName] ?? 0) + 1;
            $address = self::subdivisionAddressCatalog()[$subdivisionName] ?? [];
            $address['address_house'] = (string) $houseCounters[$subdivisionName];

            Warehouse::query()->updateOrCreate(
                [
                    'subdivision_id' => $subdivision->id,
                    'name' => $row['name'],
                ],
                array_merge(
                    [
                        'warehouse_type_id' => $warehouseTypeId,
                        'comment' => $row['comment'] !== null && $row['comment'] !== '' ? $row['comment'] : null,
                        'is_primary' => false,
                    ],
                    $address
                )
            );
        }
    }

    /**
     * Адреса складов по подразделениям (Иркутская область и города области).
     * Поле address_house задаётся при сиде по порядку складов в подразделении (1, 2, 3…).
     *
     * @return array<string, array{
     *     address_postal_code: string,
     *     address_region: string,
     *     address_city: string,
     *     address_street: string,
     *     address_block?: string|null,
     *     address_flat?: string|null
     * }>
     */
    public static function subdivisionAddressCatalog(): array
    {
        return [
            AdministrationWarehouse::SUBDIVISION_NAME => [
                'address_postal_code' => '664025',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'г. Иркутск',
                'address_street' => 'ул. Карла Маркса',
            ],
            'Район тепловых сетей Северный' => [
                'address_postal_code' => '664007',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'г. Иркутск',
                'address_street' => 'ул. Байкальская',
            ],
            'Район тепловых сетей Южный' => [
                'address_postal_code' => '664011',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'г. Иркутск',
                'address_street' => 'ул. Розы Люксембург',
            ],
            'Район тепловых сетей Центральный' => [
                'address_postal_code' => '664003',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'г. Иркутск',
                'address_street' => 'ул. Ленина',
            ],
            'Район тепловых сетей Восточный' => [
                'address_postal_code' => '664075',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'г. Иркутск',
                'address_street' => 'ул. Сурикова',
            ],
            'Ремонтно-механический участок' => [
                'address_postal_code' => '664047',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'г. Иркутск',
                'address_street' => 'ул. Чкалова',
            ],
            'Лаборатория технического контроля' => [
                'address_postal_code' => '664056',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'г. Иркутск',
                'address_street' => 'ул. Дзержинского',
            ],
            'Киренск правый берег' => [
                'address_postal_code' => '666703',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'г. Киренск',
                'address_street' => 'ул. Ленина',
            ],
            'Киренск левый берег' => [
                'address_postal_code' => '666704',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'г. Киренск',
                'address_street' => 'ул. Советская',
            ],
            'Игирма больница' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. Больничная',
            ],
            'Игирма Инвест котлы СЭЛ' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. Заводская',
            ],
            'Игирма Инвест КТР' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. Центральная',
            ],
            'Игирма Инвест т/с Киевский' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. Киевская',
            ],
            'Игирма КАП' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. Промышленная',
            ],
            'Игирма КАПЫ из тарифа' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. Тарифная',
            ],
            'Игирма КР ГВС и ЦТП' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. Тепловая',
            ],
            'Игирма КР кот Киевская' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'пер. Котельный',
            ],
            'Игирма КР кот СЭЛ' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. СЭЛ',
            ],
            'Игирма КР т/с Киевская' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. Киевская',
            ],
            'Игирма КР т/с СЭЛ' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. СЭЛ',
            ],
            'Игирма КР т/с Химки' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. Химкинская',
            ],
            'Игирма КР школа' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. Школьная',
            ],
            'Игирма ЦТП Киевский' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. Киевская',
            ],
            'ИГИРМА теплотрасса' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Игирма',
                'address_street' => 'ул. Трассовая',
            ],
            'ИНК' => [
                'address_postal_code' => '666679',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'г. Усть-Илимск',
                'address_street' => 'пр-т Дружбы Народов',
            ],
            'Ручей' => [
                'address_postal_code' => '666679',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'п. Ручей',
                'address_street' => 'ул. Лесная',
            ],
            'Усть-Кут' => [
                'address_postal_code' => '666780',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'г. Усть-Кут',
                'address_street' => 'ул. Речная',
            ],
            'ЯНТАЛЬ' => [
                'address_postal_code' => '666504',
                'address_region' => 'Иркутская обл.',
                'address_city' => 'рп. Янталь',
                'address_street' => 'ул. Советская',
            ],
        ];
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
