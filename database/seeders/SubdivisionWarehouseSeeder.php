<?php

// начальные данные для базы
namespace Database\Seeders;

use App\Models\Subdivision;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Illuminate\Database\Seeder;
class SubdivisionWarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $defaultType = WarehouseType::query()->firstOrCreate(
            ['name' => 'Оптовый склад'],
            []
        );

        $addressCatalog = WarehouseSeeder::subdivisionAddressCatalog();

        foreach (Subdivision::query()->orderBy('id')->cursor() as $subdivision) {
            $existing = Warehouse::query()
                ->where('subdivision_id', $subdivision->id)
                ->count();

            if ($existing >= 2) {
                continue;
            }

            $toAdd = match (true) {
                $existing === 0 => random_int(1, 2),
                $existing === 1 => random_int(0, 1),
                default => 0,
            };

            for ($n = 1; $n <= $toAdd; $n++) {
                $slot = $existing + $n;
                $finalTotal = $existing + $toAdd;
                $name = $finalTotal === 1
                    ? 'Склад'
                    : 'Склад №'.$slot;

                $address = $addressCatalog[$subdivision->name] ?? [];
                $address['address_house'] = (string) $slot;

                Warehouse::query()->updateOrCreate(
                    [
                        'subdivision_id' => $subdivision->id,
                        'name' => $name,
                    ],
                    array_merge(
                        [
                            'warehouse_type_id' => $defaultType->id,
                            'is_primary' => false,
                            'comment' => 'Подразделение: '.$subdivision->name,
                        ],
                        $address
                    )
                );
            }
        }
    }
}
