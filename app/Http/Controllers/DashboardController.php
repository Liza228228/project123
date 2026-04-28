<?php

namespace App\Http\Controllers;

use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $mainWarehouse = $this->resolveMainWarehouse();
        $mainWarehouseId = $mainWarehouse?->id;

        $mainWarehouseBalances = collect();
        $deficitPositions = collect();

        if ($mainWarehouseId) {
            $mainWarehouseBalances = $this->baseBalanceQuery($mainWarehouseId)
                ->havingRaw('ABS(balance) >= 0.0005')
                ->orderByDesc('balance')
                ->limit(10)
                ->get();

            $deficitPositions = $this->baseBalanceQuery($mainWarehouseId)
                ->havingRaw('balance <= 0.0005')
                ->havingRaw('qty_out > 0.0005')
                ->orderBy('balance')
                ->limit(10)
                ->get();
        }

        $latestOperations = MaterialStockMovement::query()
            ->with(['equipment:id,name,measurement_unit_id', 'equipment.measurementUnit:id,code', 'warehouse:id,name', 'movementType:id,name'])
            ->when($mainWarehouseId, fn ($query) => $query->where('warehouse_id', $mainWarehouseId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('dashboard', [
            'mainWarehouse' => $mainWarehouse,
            'mainWarehouseBalances' => $mainWarehouseBalances,
            'deficitPositions' => $deficitPositions,
            'latestOperations' => $latestOperations,
        ]);
    }

    private function baseBalanceQuery(int $warehouseId)
    {
        $r = MaterialStockMovementType::NAME_RECEIPT;
        $i = MaterialStockMovementType::NAME_ISSUE;
        $a = MaterialStockMovementType::NAME_ADJUSTMENT;

        return MaterialStockMovement::query()
            ->join('equipment', 'equipment.id', '=', 'material_stock_movements.equipment_id')
            ->join('material_stock_movement_types as msm_types', 'msm_types.id', '=', 'material_stock_movements.material_stock_movement_type_id')
            ->leftJoin('measurement_units', 'measurement_units.id', '=', 'equipment.measurement_unit_id')
            ->where('material_stock_movements.warehouse_id', $warehouseId)
            ->groupBy('equipment.id', 'equipment.name', 'measurement_units.code')
            ->selectRaw('equipment.id as equipment_id')
            ->selectRaw('equipment.name as equipment_name')
            ->selectRaw("COALESCE(measurement_units.code, 'шт') as unit_code")
            ->selectRaw('SUM(CASE WHEN msm_types.name = ? THEN material_stock_movements.quantity WHEN msm_types.name = ? AND material_stock_movements.quantity > 0 THEN material_stock_movements.quantity ELSE 0 END) as qty_in', [$r, $a])
            ->selectRaw('SUM(CASE WHEN msm_types.name = ? THEN material_stock_movements.quantity WHEN msm_types.name = ? AND material_stock_movements.quantity < 0 THEN -material_stock_movements.quantity ELSE 0 END) as qty_out', [$i, $a])
            ->selectRaw('SUM(CASE WHEN msm_types.name = ? THEN -material_stock_movements.quantity ELSE material_stock_movements.quantity END) as balance', [$i]);
    }

    private function resolveMainWarehouse(): ?Warehouse
    {
        return Warehouse::query()
            ->where('is_primary', true)
            ->orderBy('id')
            ->first();
    }
}
