<?php

namespace App\Http\Controllers;

use App\Models\MaterialStockMovement;
use App\Models\Equipment;
use App\Models\MeasurementUnit;
use App\Models\Warehouse;
use App\Models\UnitType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaterialAccountingController extends Controller
{
    public function index(Request $request): View
    {
        $warehouseFilter = $request->integer('warehouse_id');
        $selectedWarehouseId = $warehouseFilter > 0 ? $warehouseFilter : null;
        $mainWarehouse = $this->resolveMainWarehouse();

        $warehouses = Warehouse::query()
            ->with('subdivision:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'subdivision_id']);

        $materials = Equipment::query()
            ->with('measurementUnit:id,code')
            ->orderBy('name')
            ->get();

        $measurementUnitsByType = MeasurementUnit::query()
            ->with('unitType:id,code')
            ->orderBy('unit_type_id')
            ->orderBy('id')
            ->get(['id', 'unit_type_id', 'code', 'name'])
            ->groupBy(fn (MeasurementUnit $unit) => (string) ($unit->unitType?->code ?? ''))
            ->map(fn ($group) => $group->map(fn (MeasurementUnit $unit): array => [
                'id' => (int) $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
            ])->values()->all())
            ->all();
        $measurementTypeOptions = UnitType::query()
            ->orderBy('id')
            ->get(['code', 'name'])
            ->mapWithKeys(fn (UnitType $type) => [(string) $type->code => (string) $type->name])
            ->all();

        $balances = $this->buildMaterialBalances($selectedWarehouseId);

        $movementsQuery = MaterialStockMovement::query()
            ->with(['equipment:id,name,measurement_unit_id', 'equipment.measurementUnit:id,code', 'warehouse:id,name', 'createdBy:id,surname,name,patronymic'])
            ->orderByDesc('happened_at')
            ->orderByDesc('id');

        if ($selectedWarehouseId !== null) {
            $movementsQuery->where('warehouse_id', $selectedWarehouseId);
        }

        $movements = $movementsQuery->paginate(30)->withQueryString();

        return view('materials.index', compact(
            'warehouses',
            'materials',
            'balances',
            'movements',
            'selectedWarehouseId',
            'mainWarehouse',
            'measurementUnitsByType',
            'measurementTypeOptions'
        ));
    }

    public function storeMaterial(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'base_name' => ['required', 'string', 'max:120'],
            'size_value' => ['nullable', 'string', 'max:120'],
            'measurement_type' => ['required', Rule::in(UnitType::query()->pluck('code')->all())],
            'measurement_unit_id' => ['required', 'integer', 'exists:measurement_units,id'],
        ]);

        $unit = MeasurementUnit::query()->findOrFail((int) $validated['measurement_unit_id']);
        $unitTypeCode = (string) ($unit->unitType?->code ?? '');
        if ($unitTypeCode !== $validated['measurement_type']) {
            throw ValidationException::withMessages([
                'measurement_unit_id' => 'Единица измерения не соответствует выбранному типу.',
            ]);
        }

        $baseName = trim((string) $validated['base_name']);
        $sizeValue = trim((string) ($validated['size_value'] ?? ''));
        $name = trim($baseName.' '.$sizeValue);

        if (Equipment::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw ValidationException::withMessages([
                'base_name' => 'Такое оборудование уже есть в справочнике.',
            ]);
        }

        Equipment::query()->create([
            'name' => $name,
            'base_name' => $baseName,
            'size_value' => $sizeValue !== '' ? $sizeValue : null,
            'measurement_unit_id' => (int) $validated['measurement_unit_id'],
        ]);

        return redirect()
            ->route('materials.index')
            ->with('status', 'Оборудование добавлено в справочник.');
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'type' => ['required', Rule::in(['receipt', 'issue', 'adjustment'])],
            'quantity' => ['required', 'numeric'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'happened_at' => ['required', 'date'],
            'document_ref' => ['nullable', 'string', 'max:100'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $type = (string) $validated['type'];
        $quantity = (float) $validated['quantity'];
        $mainWarehouse = $this->resolveMainWarehouse();

        if ($type === 'receipt') {
            if (! $mainWarehouse) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Не найден основной склад "Администрация". Назначьте его основным.',
                ]);
            }
            $validated['warehouse_id'] = $mainWarehouse->id;
        }

        if (in_array($type, ['receipt', 'issue'], true) && $quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Для прихода и расхода количество должно быть больше нуля.',
            ]);
        }

        if ($type === 'adjustment' && abs($quantity) < 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Для корректировки укажите положительное или отрицательное значение.',
            ]);
        }

        DB::transaction(function () use ($validated, $request, $type, $quantity) {
            if ($type === 'issue') {
                $balance = $this->currentBalance((int) $validated['equipment_id'], (int) $validated['warehouse_id']);
                if ($balance < $quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Недостаточно остатка на складе. Доступно: '.number_format($balance, 3, '.', ' '),
                    ]);
                }
            }

            MaterialStockMovement::query()->create([
                'equipment_id' => (int) $validated['equipment_id'],
                'warehouse_id' => (int) $validated['warehouse_id'],
                'type' => $type,
                'quantity' => $quantity,
                'unit_price' => $validated['unit_price'] ?? null,
                'happened_at' => $validated['happened_at'],
                'document_ref' => isset($validated['document_ref']) ? trim((string) $validated['document_ref']) : null,
                'counterparty' => isset($validated['counterparty']) ? trim((string) $validated['counterparty']) : null,
                'comment' => isset($validated['comment']) ? trim((string) $validated['comment']) : null,
                'created_by_user_id' => $request->user()->id,
            ]);
        });

        return redirect()
            ->route('materials.index', $request->only('warehouse_id'))
            ->with('status', 'Операция по оборудованию сохранена.');
    }

    /**
     * @return array<int, array{in: float, out: float, balance: float}>
     */
    private function buildMaterialBalances(?int $warehouseId = null): array
    {
        $query = MaterialStockMovement::query()
            ->selectRaw('equipment_id')
            ->selectRaw("SUM(CASE WHEN type = 'receipt' THEN quantity WHEN type = 'adjustment' AND quantity > 0 THEN quantity ELSE 0 END) as qty_in")
            ->selectRaw("SUM(CASE WHEN type = 'issue' THEN quantity WHEN type = 'adjustment' AND quantity < 0 THEN -quantity ELSE 0 END) as qty_out")
            ->selectRaw("SUM(CASE WHEN type = 'issue' THEN -quantity ELSE quantity END) as qty_balance")
            ->groupBy('equipment_id');

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        $rows = $query->get();
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->equipment_id] = [
                'in' => (float) $row->qty_in,
                'out' => (float) $row->qty_out,
                'balance' => (float) $row->qty_balance,
            ];
        }

        return $result;
    }

    private function currentBalance(int $equipmentId, int $warehouseId): float
    {
        $balance = MaterialStockMovement::query()
            ->where('equipment_id', $equipmentId)
            ->where('warehouse_id', $warehouseId)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'issue' THEN -quantity ELSE quantity END), 0) as balance")
            ->value('balance');

        return (float) $balance;
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
}
