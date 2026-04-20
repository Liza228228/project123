<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\MaterialStockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class MainWarehouseStockSeeder extends Seeder
{
    public function run(): void
    {
        $mainWarehouse = $this->resolveMainWarehouse();
        if (! $mainWarehouse) {
            return;
        }

        $actorId = (int) User::query()->orderBy('id')->value('id');
        if ($actorId <= 0) {
            return;
        }

        $equipment = Equipment::query()
            ->where('is_catalog', true)
            ->with('measurementUnit:id,code')
            ->orderBy('id')
            ->get(['id', 'measurement_unit_id']);

        foreach ($equipment as $item) {
            $quantity = $this->defaultQuantityForUnitCode((string) ($item->measurementUnit?->code ?? 'шт'));
            $documentRef = 'INIT-STOCK:WH:'.$mainWarehouse->id.':EQ:'.$item->id;

            MaterialStockMovement::query()->updateOrCreate(
                [
                    'warehouse_id' => (int) $mainWarehouse->id,
                    'equipment_id' => (int) $item->id,
                    'type' => 'receipt',
                    'document_ref' => $documentRef,
                ],
                [
                    'quantity' => $quantity,
                    'unit_price' => null,
                    'happened_at' => now(),
                    'counterparty' => 'Начальные остатки',
                    'comment' => 'Первичное наполнение основного склада из сидера.',
                    'created_by_user_id' => $actorId,
                ]
            );
        }
    }

    private function resolveMainWarehouse(): ?Warehouse
    {
        $primary = Warehouse::query()
            ->where('is_primary', true)
            ->orderBy('id')
            ->first();
        if ($primary) {
            return $primary;
        }

        return Warehouse::query()
            ->whereRaw('LOWER(name) like ?', ['%администрац%'])
            ->orderBy('id')
            ->first();
    }

    private function defaultQuantityForUnitCode(string $unitCode): float
    {
        $unit = mb_strtolower(trim($unitCode));

        return match ($unit) {
            'м', 'км' => 30.000,
            'кг', 'т', 'л' => 100.000,
            default => 100.000,
        };
    }
}
