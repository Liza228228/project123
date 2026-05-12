<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\MeasurementUnit;
use App\Models\Subdivision;
use App\Models\UnitType;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\MaterialsListPerPage;
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
        $user = $request->user();
        $canManage = $user?->hasAnyRoleId([1, 6, 2]) ?? false;

        return $this->renderIndex($request, $canManage);
    }

    public function movementsJournal(Request $request): View
    {
        $user = $request->user();
        $canManage = $user?->hasAnyRoleId([1, 6, 2]) ?? false;

        $warehouseFilter = $request->integer('warehouse_id');
        $selectedWarehouseId = $warehouseFilter > 0 ? $warehouseFilter : null;

        $warehouses = Warehouse::query()
            ->with('subdivision:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'subdivision_id']);

        $movementTypes = MaterialStockMovementType::query()
            ->orderBy('id')
            ->get(['id', 'name']);

        $requestedMovementTypeId = $request->integer('movement_type_id');
        $selectedMovementTypeId = null;
        if ($requestedMovementTypeId > 0 && $movementTypes->contains(fn (MaterialStockMovementType $t): bool => (int) $t->id === $requestedMovementTypeId)) {
            $selectedMovementTypeId = $requestedMovementTypeId;
        }

        $movementsQuery = MaterialStockMovement::query()
            ->with([
                'equipment:id,name,measurement_unit_id',
                'equipment.measurementUnit:id,code,unit_type_id',
                'equipment.measurementUnit.unitType:id,code',
                'warehouse:id,name',
                'movementType:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($selectedWarehouseId !== null) {
            $movementsQuery->where('warehouse_id', $selectedWarehouseId);
        }

        if ($selectedMovementTypeId !== null) {
            $movementsQuery->where('material_stock_movement_type_id', $selectedMovementTypeId);
        }

        $journalPerPage = MaterialsListPerPage::fromRequest($request, 'journal');
        $movements = $movementsQuery->paginate($journalPerPage['perPage'])->withQueryString();

        $materialsJournalBackUrl = $this->materialsJournalBackUrl($user, $selectedWarehouseId);

        return view('materials.movements-journal', compact(
            'canManage',
            'warehouses',
            'movementTypes',
            'movements',
            'selectedWarehouseId',
            'selectedMovementTypeId',
            'materialsJournalBackUrl',
        ) + $journalPerPage);
    }

    /**
     * «Учёт оборудования» (supply_head) или «Остатки по складам» (остальные роли с /materials/overview).
     */
    private function materialsJournalBackUrl(?User $user, ?int $selectedWarehouseId): string
    {
        if ($user?->hasAnyRoleId([1, 6, 2, 3])) {
            return route('materials.index', array_filter(['warehouse_id' => $selectedWarehouseId]));
        }

        $query = [];
        if ($selectedWarehouseId) {
            $warehouse = Warehouse::query()->find($selectedWarehouseId);
            if ($warehouse) {
                $query['subdivision_id'] = (int) $warehouse->subdivision_id;
                $query['warehouse_id'] = (int) $warehouse->id;
            }
        }

        return route('materials.overview', $query);
    }

    public function overview(Request $request): View
    {
        $user = $request->user();
        $selectedSubdivisionId = $request->integer('subdivision_id');
        $selectedWarehouseId = $request->integer('warehouse_id');
        $mainWarehouse = $this->resolveMainWarehouse();
        $hasExplicitFilters = $request->filled('subdivision_id') || $request->filled('warehouse_id');
        $usingDefaultMainWarehouse = false;

        if (! $hasExplicitFilters && $mainWarehouse) {
            $selectedSubdivisionId = (int) $mainWarehouse->subdivision_id;
            $selectedWarehouseId = (int) $mainWarehouse->id;
            $usingDefaultMainWarehouse = true;
        }

        $subdivisionsQuery = Subdivision::query()->orderBy('name');
        if ($user->hasRoleId(4)) {
            $subdivisionIds = $user->assignedSubdivisions()->pluck('subdivisions.id');
            $subdivisionsQuery->whereIn('id', $subdivisionIds);
        } elseif ($user->hasRoleId(7)) {
            $subdivisionIds = $user->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $subdivisionsQuery->whereIn('id', $subdivisionIds);
        }

        $subdivisions = $subdivisionsQuery
            ->withCount('warehouses')
            ->get(['id', 'name']);

        $selectedSubdivision = $subdivisions->firstWhere('id', $selectedSubdivisionId);
        if (! $selectedSubdivision) {
            $selectedSubdivisionId = 0;
            $selectedWarehouseId = 0;
        }

        $warehouses = collect();
        if ($selectedSubdivisionId > 0) {
            $warehouses = Warehouse::query()
                ->where('subdivision_id', $selectedSubdivisionId)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'subdivision_id']);
        }

        $selectedWarehouse = $warehouses->firstWhere('id', $selectedWarehouseId);
        if (! $selectedWarehouse) {
            $selectedWarehouseId = 0;
            $usingDefaultMainWarehouse = false;
        }

        $balancesPerPage = MaterialsListPerPage::fromRequest($request, 'balances');

        $overviewTabQuery = [];
        if ($selectedSubdivisionId > 0) {
            $overviewTabQuery['subdivision_id'] = $selectedSubdivisionId;
        }
        if ($selectedWarehouseId > 0) {
            $overviewTabQuery['warehouse_id'] = $selectedWarehouseId;
        }
        if ($balancesPerPage['perPage'] !== $balancesPerPage['defaultPerPage']) {
            $overviewTabQuery['per_page'] = $balancesPerPage['perPage'];
        }

        $equipmentBalances = collect();
        $balancesView = 'stock';
        if ($selectedWarehouseId > 0) {
            $balancesView = $request->query('balances') === 'written' ? 'written' : 'stock';

            $balancePaginationAppends = array_merge(
                $overviewTabQuery,
                $balancesView === 'written' ? ['balances' => 'written'] : []
            );

            $epsilon = 0.0005;
            $query = $this->overviewWarehouseBalanceBaseQuery($selectedWarehouseId);
            if ($balancesView === 'written') {
                $query->havingRaw('balance <= ? AND qty_out > ?', [$epsilon, $epsilon]);
            }

            $equipmentBalances = $query
                ->orderBy('equipment.name')
                ->paginate($balancesPerPage['perPage'])
                ->appends($balancePaginationAppends);
        }

        return view('materials.overview', [
            'subdivisions' => $subdivisions,
            'selectedSubdivision' => $selectedSubdivision,
            'warehouses' => $warehouses,
            'selectedWarehouse' => $selectedWarehouse,
            'equipmentBalances' => $equipmentBalances,
            'balancesView' => $balancesView,
            'usingDefaultMainWarehouse' => $usingDefaultMainWarehouse,
            'overviewTabQuery' => $overviewTabQuery,
        ] + $balancesPerPage);
    }

    private function renderIndex(Request $request, bool $canManage): View
    {
        $warehouseFilter = $request->integer('warehouse_id');
        $selectedWarehouseId = $warehouseFilter > 0 ? $warehouseFilter : null;
        $mainWarehouse = $this->resolveMainWarehouse();

        $balancesPerPage = MaterialsListPerPage::fromRequest($request, 'balances');

        $warehouses = Warehouse::query()
            ->with('subdivision:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'subdivision_id']);

        $materialsQuery = Equipment::query()
            ->where('is_catalog', true)
            ->with(['measurementUnit:id,code,unit_type_id', 'measurementUnit.unitType:id,code'])
            ->orderBy('name');

        if ($canManage) {
            $catalogMaterials = (clone $materialsQuery)->get();
            $materialsBalancesPaginator = (clone $materialsQuery)->paginate($balancesPerPage['perPage'])->withQueryString();
        } else {
            $catalogMaterials = null;
            $materialsBalancesPaginator = $materialsQuery->paginate($balancesPerPage['perPage'])->withQueryString();
        }

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

        $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);

        $clothingCatalogSizes = $this->clothingCatalogSizeOptions();

        return view('materials.index', compact(
            'canManage',
            'warehouses',
            'catalogMaterials',
            'materialsBalancesPaginator',
            'balances',
            'selectedWarehouseId',
            'mainWarehouse',
            'measurementUnitsByType',
            'measurementTypeOptions',
            'receiptTypeId',
            'clothingCatalogSizes',
        ) + $balancesPerPage);
    }

    public function storeMaterial(Request $request): RedirectResponse
    {
        if (! $request->user()?->hasAnyRoleId([1, 6, 2])) {
            abort(403, 'Добавление оборудования доступно только директору, техническому директору и начальнику отдела снабжения.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
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

        $name = trim((string) $validated['name']);

        if (Equipment::query()->whereRaw('LOWER(name) = LOWER(?)', [$name])->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Оборудование с таким названием уже есть в справочнике. Укажите другое название.',
            ]);
        }

        Equipment::query()->create([
            'name' => $name,
            'value' => null,
            'measurement_unit_id' => (int) $validated['measurement_unit_id'],
            'is_catalog' => true,
        ]);

        return redirect()
            ->route('materials.index')
            ->with('status', 'Оборудование добавлено в справочник.');
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        if (! $request->user()?->hasAnyRoleId([1, 6, 2])) {
            abort(403, 'Операции по складу доступны только директору, техническому директору и начальнику отдела снабжения.');
        }

        $validated = $request->validate([
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'material_stock_movement_type_id' => ['required', 'integer', 'exists:material_stock_movement_types,id'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $equipment = Equipment::query()
            ->with(['measurementUnit.unitType:id,code'])
            ->findOrFail((int) $validated['equipment_id']);

        $typeModel = MaterialStockMovementType::query()->findOrFail((int) $validated['material_stock_movement_type_id']);
        $typeName = (string) $typeModel->name;

        $equipmentUnitType = (string) ($equipment->measurementUnit?->unitType?->code ?? '');

        if ($equipmentUnitType === 'clothing_size' && $typeName === MaterialStockMovementType::NAME_RECEIPT) {
            $validated = array_merge(
                $validated,
                $request->validate([
                    'receipt_variant' => ['required', Rule::in($this->clothingCatalogSizeOptions())],
                ])
            );
            $validated['quantity'] = 1.0;
            $validated['receipt_variant'] = trim((string) $validated['receipt_variant']);
        } else {
            $validated = array_merge(
                $validated,
                $request->validate([
                    'quantity' => ['required', 'numeric'],
                ])
            );
            $validated['receipt_variant'] = null;
        }

        if (in_array($equipmentUnitType, ['piece', 'mass', 'length'], true)) {
            $qtyRaw = (string) $request->input('quantity');
            if (preg_match('/\p{L}/u', $qtyRaw)) {
                throw ValidationException::withMessages([
                    'quantity' => 'Для оборудования с типом учёта «штуки», «масса» или «длина» в количестве не допускаются буквы — только число.',
                ]);
            }
        }

        $type = $typeName;
        $quantity = (float) $validated['quantity'];
        $mainWarehouse = $this->resolveMainWarehouse();

        if ($type === MaterialStockMovementType::NAME_RECEIPT) {
            if (! $mainWarehouse) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Не найден основной склад "Администрация". Назначьте его основным.',
                ]);
            }
            $validated['warehouse_id'] = $mainWarehouse->id;
        }

        if (in_array($type, [MaterialStockMovementType::NAME_RECEIPT, MaterialStockMovementType::NAME_ISSUE], true) && $quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Для прихода и расхода количество должно быть больше нуля.',
            ]);
        }

        if ($type === MaterialStockMovementType::NAME_ADJUSTMENT && abs($quantity) < 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Для корректировки укажите положительное или отрицательное значение.',
            ]);
        }

        $receiptVariant = $validated['receipt_variant'] ?? null;

        DB::transaction(function () use ($validated, $type, $quantity, $receiptVariant) {
            if ($type === MaterialStockMovementType::NAME_ISSUE) {
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
                'material_stock_movement_type_id' => (int) $validated['material_stock_movement_type_id'],
                'quantity' => $quantity,
                'receipt_variant' => $receiptVariant,
                'unit_price' => $validated['unit_price'] ?? null,
                'counterparty' => isset($validated['counterparty']) ? trim((string) $validated['counterparty']) : null,
                'comment' => isset($validated['comment']) ? trim((string) $validated['comment']) : null,
            ]);
        });

        return redirect()
            ->route('materials.index', array_filter(
                $request->only(['warehouse_id', 'per_page']),
                static fn ($v) => $v !== null && $v !== ''
            ))
            ->with('status', 'Операция по оборудованию сохранена.');
    }

    /**
     * Агрегат приход / списание / остаток по оборудованию на одном складе (без фильтра по остатку).
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\MaterialStockMovement>
     */
    private function overviewWarehouseBalanceBaseQuery(int $warehouseId): \Illuminate\Database\Eloquent\Builder
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

    /**
     * @return array<int, array{in: float, out: float, balance: float}>
     */
    private function buildMaterialBalances(?int $warehouseId = null): array
    {
        $r = MaterialStockMovementType::NAME_RECEIPT;
        $i = MaterialStockMovementType::NAME_ISSUE;
        $a = MaterialStockMovementType::NAME_ADJUSTMENT;

        $query = MaterialStockMovement::query()
            ->join('material_stock_movement_types as msm_types', 'msm_types.id', '=', 'material_stock_movements.material_stock_movement_type_id')
            ->selectRaw('material_stock_movements.equipment_id as equipment_id')
            ->selectRaw('SUM(CASE WHEN msm_types.name = ? THEN material_stock_movements.quantity WHEN msm_types.name = ? AND material_stock_movements.quantity > 0 THEN material_stock_movements.quantity ELSE 0 END) as qty_in', [$r, $a])
            ->selectRaw('SUM(CASE WHEN msm_types.name = ? THEN material_stock_movements.quantity WHEN msm_types.name = ? AND material_stock_movements.quantity < 0 THEN -material_stock_movements.quantity ELSE 0 END) as qty_out', [$i, $a])
            ->selectRaw('SUM(CASE WHEN msm_types.name = ? THEN -material_stock_movements.quantity ELSE material_stock_movements.quantity END) as qty_balance', [$i])
            ->groupBy('material_stock_movements.equipment_id');

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
        $issueId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
        $balance = MaterialStockMovement::query()
            ->where('equipment_id', $equipmentId)
            ->where('warehouse_id', $warehouseId)
            ->selectRaw('COALESCE(SUM(CASE WHEN material_stock_movement_type_id = ? THEN -quantity ELSE quantity END), 0) as balance', [$issueId])
            ->value('balance');

        return (float) $balance;
    }

    private function resolveMainWarehouse(): ?Warehouse
    {
        return Warehouse::query()
            ->where('is_primary', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    private function clothingCatalogSizeOptions(): array
    {
        return ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '4XL', '5XL'];
    }
}
