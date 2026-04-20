<?php

namespace Database\Seeders;

use App\Models\Subdivision;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Illuminate\Database\Seeder;

/**
 * После WarehouseDataFrom1CsvSeeder: у каждого подразделения не меньше 1 и не больше 2 складов
 * из этого сидера. Уже существующие склады (в т.ч. из CSV) не удаляются; добавляются только
 * недостающие, если у подразделения 0 или 1 склад.
 */
class SubdivisionWarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $defaultType = WarehouseType::query()->firstOrCreate(
            ['name' => 'Оптовый склад'],
            []
        );

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
                $code = $this->makeWarehouseCode($subdivision->id, $slot);
                $finalTotal = $existing + $toAdd;
                $name = $finalTotal === 1
                    ? 'Склад'
                    : 'Склад №'.$slot;

                Warehouse::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'subdivision_id' => $subdivision->id,
                        'warehouse_type_id' => $defaultType->id,
                        'is_primary' => false,
                        'comment' => 'Подразделение: '.$subdivision->name,
                    ]
                );
            }
        }
    }

    private function makeWarehouseCode(int $subdivisionId, int $index): string
    {
        $readable = sprintf('S%dW%d', $subdivisionId, $index);

        if (strlen($readable) <= 10) {
            return $readable;
        }

        return strtoupper(substr(hash('sha256', 'wh|'.$subdivisionId.'|'.$index), 0, 10));
    }
}
