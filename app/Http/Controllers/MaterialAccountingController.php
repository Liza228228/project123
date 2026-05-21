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
use App\Support\AdministrationWarehouse;
use App\Support\PieceQuantity;
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
        $canManage = $user?->hasAnyRoleId(User::MATERIALS_CATALOG_RECEIPT_ROLE_IDS) ?? false;

        return $this->renderIndex($request, $canManage);
    }

    public function movementsJournal(Request $request): View
    {
        $user = $request->user();
        $canManage = $user?->hasAnyRoleId(User::MATERIALS_CATALOG_RECEIPT_ROLE_IDS) ?? false;

        $scopedWarehouseIds = $this->materialsJournalWarehouseIdsScopedToUserSubdivisions($user);

        $warehouseFilter = $request->integer('warehouse_id');
        $selectedWarehouseId = $warehouseFilter > 0 ? $warehouseFilter : null;
        if ($scopedWarehouseIds !== null && $selectedWarehouseId !== null && ! in_array($selectedWarehouseId, $scopedWarehouseIds, true)) {
            $selectedWarehouseId = null;
        }

        $warehousesQuery = Warehouse::query()
            ->with('subdivision:id,name')
            ->orderBy('name');

        if (! AdministrationWarehouse::userCanAccess($user)) {
            AdministrationWarehouse::excludeAdministrationWarehouse($warehousesQuery);
        }

        if ($scopedWarehouseIds !== null) {
            if ($scopedWarehouseIds === []) {
                $warehousesQuery->whereRaw('0 = 1');
            } else {
                $warehousesQuery->whereIn('id', $scopedWarehouseIds);
            }
        }

        $warehouses = $warehousesQuery->get(['id', 'name', 'subdivision_id']);

        $movementTypes = MaterialStockMovementType::query()
            ->whereIn('name', [
                MaterialStockMovementType::NAME_RECEIPT,
                MaterialStockMovementType::NAME_ISSUE,
            ])
            ->orderBy('id')
            ->get(['id', 'name']);

        $requestedMovementTypeId = $request->integer('movement_type_id');
        $selectedMovementTypeId = null;
        if ($requestedMovementTypeId > 0 && $movementTypes->contains(fn (MaterialStockMovementType $t): bool => (int) $t->id === $requestedMovementTypeId)) {
            $selectedMovementTypeId = $requestedMovementTypeId;
        }

        $movementsQuery = MaterialStockMovement::query()
            ->with([
                'equipment:id,name,value,measurement_unit_id',
                'equipment.measurementUnit:id,code,unit_type_id',
                'equipment.measurementUnit.unitType:id,code',
                'warehouse:id,name,subdivision_id',
                'warehouse.subdivision:id,name',
                'movementType:id,name',
                'creator:id,surname,name,patronymic',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($scopedWarehouseIds !== null) {
            if ($scopedWarehouseIds === []) {
                $movementsQuery->whereRaw('0 = 1');
            } else {
                $movementsQuery->whereIn('warehouse_id', $scopedWarehouseIds);
            }
        }

        if ($selectedWarehouseId !== null) {
            $movementsQuery->where('warehouse_id', $selectedWarehouseId);
        }

        if ($selectedMovementTypeId !== null) {
            $movementsQuery->where('material_stock_movement_type_id', $selectedMovementTypeId);
        }

        $journalPerPage = MaterialsListPerPage::fromRequest($request, 'journal');
        $movements = $movementsQuery->paginate($journalPerPage['perPage'])->withQueryString();

        $materialsJournalBackUrl = $this->materialsJournalBackUrl($user, $selectedWarehouseId);

        $materialsJournalSubdivisionScoped = $scopedWarehouseIds !== null;
        $mainWarehouseForJournalContext = $materialsJournalSubdivisionScoped ? $this->resolveMainWarehouse() : null;

        return view('materials.movements-journal', compact(
            'canManage',
            'warehouses',
            'movementTypes',
            'movements',
            'selectedWarehouseId',
            'selectedMovementTypeId',
            'materialsJournalBackUrl',
            'materialsJournalSubdivisionScoped',
            'mainWarehouseForJournalContext',
        ) + $journalPerPage);
    }

    /**
     * Склады, по которым мастер участка / начальник котельной может видеть журнал (только свои подразделения).
     *
     * @return list<int>|null null — без ограничения (прочие роли).
     */
    private function materialsJournalWarehouseIdsScopedToUserSubdivisions(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        if ($user->hasRoleId(4)) {
            $subdivisionIds = $user->assignedSubdivisions()
                ->pluck('subdivisions.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        } elseif ($user->hasRoleId(7)) {
            $subdivisionIds = $user->boilerChiefSubdivisions()
                ->pluck('subdivisions.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        } else {
            return null;
        }

        if ($subdivisionIds === []) {
            return [];
        }

        return Warehouse::query()
            ->whereIn('subdivision_id', $subdivisionIds)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * «Учёт оборудования» (роли {@see User::MATERIALS_CATALOG_RECEIPT_ROLE_IDS} и бухгалтер для просмотра) или «Остатки по складам».
     */
    private function materialsJournalBackUrl(?User $user, ?int $selectedWarehouseId): string
    {
        if ($user?->hasAnyRoleId([1, 2, 3])) {
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
        $canAccessAdministration = AdministrationWarehouse::userCanAccess($user);
        $mainWarehouse = $canAccessAdministration ? $this->resolveMainWarehouse() : null;
        $hasExplicitFilters = $request->filled('subdivision_id') || $request->filled('warehouse_id');
        $usingDefaultMainWarehouse = false;

        if (! $hasExplicitFilters && $mainWarehouse) {
            $selectedSubdivisionId = (int) $mainWarehouse->subdivision_id;
            $selectedWarehouseId = (int) $mainWarehouse->id;
            $usingDefaultMainWarehouse = true;
        }

        if ($selectedWarehouseId > 0 && ! $canAccessAdministration && AdministrationWarehouse::isAdministrationWarehouseId($selectedWarehouseId)) {
            abort(403, 'Просмотр остатков складов подразделения «Администрация» доступен только директору, техническому директору и начальнику отдела снабжения.');
        }

        $subdivisionsQuery = Subdivision::query()->orderBy('name');
        if (! $canAccessAdministration) {
            AdministrationWarehouse::excludeAdministrationSubdivision($subdivisionsQuery);
        }
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
                ->get(['id', 'name', 'subdivision_id']);
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

        $warehousesQuery = Warehouse::query()
            ->with('subdivision:id,name')
            ->orderBy('name');
        if (! AdministrationWarehouse::userCanAccess($request->user())) {
            AdministrationWarehouse::excludeAdministrationWarehouse($warehousesQuery);
        }
        $warehouses = $warehousesQuery->get(['id', 'name', 'subdivision_id']);

        $catalogMaterialsQuery = Equipment::query()
            ->where('is_catalog', true)
            ->with(['measurementUnit:id,code,unit_type_id', 'measurementUnit.unitType:id,code'])
            ->orderBy('name');

        $materialsBalancesQuery = Equipment::query()
            ->where(function ($q) use ($selectedWarehouseId): void {
                $q->where('is_catalog', true)
                    ->orWhereExists(function ($sub) use ($selectedWarehouseId): void {
                        $sub->selectRaw('1')
                            ->from('material_stock_movements')
                            ->whereColumn('material_stock_movements.equipment_id', 'equipment.id');
                        if ($selectedWarehouseId !== null) {
                            $sub->where('material_stock_movements.warehouse_id', $selectedWarehouseId);
                        }
                    });
            })
            ->with(['measurementUnit:id,code,unit_type_id', 'measurementUnit.unitType:id,code'])
            ->orderBy('name');

        if ($canManage) {
            $catalogMaterials = (clone $catalogMaterialsQuery)->get();
            $materialsBalancesPaginator = (clone $materialsBalancesQuery)->paginate($balancesPerPage['perPage'])->withQueryString();
        } else {
            $catalogMaterials = null;
            $materialsBalancesPaginator = $materialsBalancesQuery->paginate($balancesPerPage['perPage'])->withQueryString();
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
        $balanceLines = $this->buildMaterialBalanceLines($selectedWarehouseId);

        $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);

        $clothingCatalogSizes = $this->clothingCatalogSizeOptions();

        return view('materials.index', compact(
            'canManage',
            'warehouses',
            'catalogMaterials',
            'materialsBalancesPaginator',
            'balances',
            'balanceLines',
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
        if (! $request->user()?->hasAnyRoleId(User::MATERIALS_CATALOG_RECEIPT_ROLE_IDS)) {
            abort(403, 'Добавление оборудования доступно только директору и начальнику отдела снабжения.');
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
        if (! $request->user()?->hasAnyRoleId(User::MATERIALS_CATALOG_RECEIPT_ROLE_IDS)) {
            abort(403, 'Операции по складу доступны только директору и начальнику отдела снабжения.');
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

        if (! in_array($typeName, [MaterialStockMovementType::NAME_RECEIPT, MaterialStockMovementType::NAME_ISSUE], true)) {
            throw ValidationException::withMessages([
                'material_stock_movement_type_id' => 'Допустимы только типы «Приход» и «Списание».',
            ]);
        }

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

        if (PieceQuantity::isPieceMeasurement($equipmentUnitType)) {
            PieceQuantity::assertWholeQuantity($request->input('quantity'));
        }

        $type = $typeName;
        $quantity = PieceQuantity::isPieceMeasurement($equipmentUnitType)
            ? (float) PieceQuantity::normalizeStoredQuantity($validated['quantity'], $equipmentUnitType)
            : (float) $validated['quantity'];
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

        $receiptVariant = $validated['receipt_variant'] ?? null;

        DB::transaction(function () use ($validated, $type, $quantity, $receiptVariant, $equipment) {
            if ($type === MaterialStockMovementType::NAME_ISSUE) {
                $balance = $this->currentBalance((int) $validated['equipment_id'], (int) $validated['warehouse_id']);
                if ($balance < $quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Недостаточно остатка на складе. Доступно: '.PieceQuantity::formatForDisplay(
                            $balance,
                            $equipment->measurementUnit?->code,
                            $equipment->measurementUnit?->unitType?->code
                        ),
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
     * Для спецодежды — отдельная строка на каждый размер (S, M, L…) из прихода.
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\MaterialStockMovement>
     */
    private function overviewWarehouseBalanceBaseQuery(int $warehouseId): \Illuminate\Database\Eloquent\Builder
    {
        return $this->warehouseBalanceAggregatesQuery()
            ->where('material_stock_movements.warehouse_id', $warehouseId);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\MaterialStockMovement>
     */
    private function warehouseBalanceAggregatesQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $r = MaterialStockMovementType::NAME_RECEIPT;
        $i = MaterialStockMovementType::NAME_ISSUE;
        $clothingType = PieceQuantity::CLOTHING_MEASUREMENT_TYPE;

        $unitCodeSql = "CASE WHEN unit_types.code = '{$clothingType}' THEN COALESCE(NULLIF(TRIM(MAX(material_stock_movements.receipt_variant)), ''), NULLIF(TRIM(MAX(equipment.value)), ''), 'разм') ELSE COALESCE(MAX(measurement_units.code), 'шт') END";

        return MaterialStockMovement::query()
            ->join('equipment', 'equipment.id', '=', 'material_stock_movements.equipment_id')
            ->join('material_stock_movement_types as msm_types', 'msm_types.id', '=', 'material_stock_movements.material_stock_movement_type_id')
            ->leftJoin('measurement_units', 'measurement_units.id', '=', 'equipment.measurement_unit_id')
            ->leftJoin('unit_types', 'unit_types.id', '=', 'measurement_units.unit_type_id')
            ->groupBy(
                'equipment.id',
                'equipment.name',
                'unit_types.code',
                'measurement_units.code',
                'material_stock_movements.receipt_variant',
                'equipment.value',
            )
            ->selectRaw('equipment.id as equipment_id')
            ->selectRaw('equipment.name as equipment_name')
            ->selectRaw('unit_types.code as measurement_type_code')
            ->selectRaw("{$unitCodeSql} as unit_code")
            ->selectRaw('SUM(CASE WHEN msm_types.name = ? THEN material_stock_movements.quantity ELSE 0 END) as qty_in', [$r])
            ->selectRaw('SUM(CASE WHEN msm_types.name = ? THEN material_stock_movements.quantity ELSE 0 END) as qty_out', [$i])
            ->selectRaw('SUM(CASE WHEN msm_types.name = ? THEN -material_stock_movements.quantity ELSE material_stock_movements.quantity END) as balance', [$i]);
    }

    /**
     * @return array<int, array{in: float, out: float, balance: float}>
     */
    private function buildMaterialBalances(?int $warehouseId = null): array
    {
        $lines = $this->buildMaterialBalanceLines($warehouseId);
        $result = [];
        foreach ($lines as $equipmentId => $rows) {
            $in = 0.0;
            $out = 0.0;
            $balance = 0.0;
            foreach ($rows as $row) {
                $in += (float) $row['in'];
                $out += (float) $row['out'];
                $balance += (float) $row['balance'];
            }
            $result[$equipmentId] = [
                'in' => $in,
                'out' => $out,
                'balance' => $balance,
            ];
        }

        return $result;
    }

    /**
     * Строки остатков по оборудованию; для одежды — по размеру.
     *
     * @return array<int, list<array{in: float, out: float, balance: float, unit_code: string, measurement_type_code: string}>>
     */
    private function buildMaterialBalanceLines(?int $warehouseId = null): array
    {
        $query = $this->warehouseBalanceAggregatesQuery();
        if ($warehouseId !== null) {
            $query->where('material_stock_movements.warehouse_id', $warehouseId);
        }

        $result = [];
        foreach ($query->get() as $row) {
            $equipmentId = (int) $row->equipment_id;
            $result[$equipmentId][] = [
                'in' => (float) $row->qty_in,
                'out' => (float) $row->qty_out,
                'balance' => (float) $row->balance,
                'unit_code' => trim((string) ($row->unit_code ?? '')) ?: 'шт',
                'measurement_type_code' => trim((string) ($row->measurement_type_code ?? '')),
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
        return AdministrationWarehouse::resolvePrimaryWarehouse();
    }

    /**
     * @return list<string>
     */
    private function clothingCatalogSizeOptions(): array
    {
        return ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '4XL', '5XL'];
    }
}
