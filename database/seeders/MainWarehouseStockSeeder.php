<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
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

        $equipment = Equipment::query()
            ->where('is_catalog', true)
            ->with('measurementUnit:id,code')
            ->orderBy('id')
            ->get(['id', 'measurement_unit_id']);

        $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);

        foreach ($equipment as $item) {
            $quantity = $this->defaultQuantityForUnitCode((string) ($item->measurementUnit?->code ?? 'шт'));
            $corrKey = 'INIT-STOCK:WH:'.$mainWarehouse->id.':EQ:'.$item->id;
            $comment = MaterialStockMovement::packCommentWithCorrelation(
                $corrKey,
                'Первичное наполнение основного склада из сидера.'
            );

            $existing = MaterialStockMovement::query()
                ->where('warehouse_id', (int) $mainWarehouse->id)
                ->where('equipment_id', (int) $item->id)
                ->where('material_stock_movement_type_id', $receiptTypeId)
                ->whereCorrelationKey($corrKey)
                ->first();

            if ($existing) {
                $existing->update([
                    'quantity' => $quantity,
                    'unit_price' => null,
                    'counterparty' => 'Начальные остатки',
                    'comment' => $comment,
                ]);
            } else {
                MaterialStockMovement::query()->create([
                    'warehouse_id' => (int) $mainWarehouse->id,
                    'equipment_id' => (int) $item->id,
                    'material_stock_movement_type_id' => $receiptTypeId,
                    'quantity' => $quantity,
                    'unit_price' => null,
                    'counterparty' => 'Начальные остатки',
                    'comment' => $comment,
                ]);
            }
        }
    }

    private function resolveMainWarehouse(): ?Warehouse
    {
        return Warehouse::query()
            ->where('is_primary', true)
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
