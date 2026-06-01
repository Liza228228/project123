<?php

// учёт материалов на складах
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
use App\Support\MaterialsListPerPage;
use App\Support\PieceQuantity;
use App\Support\ReserveEquipmentDisplayName;
use App\Support\WarehouseStockBucket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaterialAccountingController extends Controller
{
    private const OVERVIEW_STOCK_FILTER_ON_WAREHOUSE = 'on_stock';

    private const OVERVIEW_STOCK_FILTER_WRITTEN_OFF = 'written_off';
    public function index(Request $request): View
    {
        $user = $request->user();
        $canManage = $user?->hasAnyRoleId(User::MATERIALS_CATALOG_RECEIPT_ROLE_IDS) ?? false;

        return $this->renderIndex($request, $canManage);
    }

    public function movementsJournal(Request $request): View
    {
        $user = $request->user();
        $this->authorizeMaterialsWarehouseSectionAccess($user);
        $canManage = $user?->hasAnyRoleId(User::MATERIALS_CATALOG_RECEIPT_ROLE_IDS) ?? false;

        $scopedWarehouseIds = $this->materialsJournalWarehouseIdsScopedToUserSubdivisions($user);

        $warehouseFilter = $request->integer('warehouse_id');
        $selectedWarehouseId = $warehouseFilter > 0 ? $warehouseFilter : null;
        if ($scopedWarehouseIds !== null && $selectedWarehouseId !== null && ! in_array($selectedWarehouseId, $scopedWarehouseIds, true)) {
            $selectedWarehouseId = null;
        }

        $warehousesQuery = Warehouse::query()
            ->inActiveSubdivision()
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
        $this->authorizeMaterialsWarehouseSectionAccess($user);
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
            abort(403, 'Просмотр остатков складов подразделения «Администрация» доступен только директору, техническому директору, начальнику отдела снабжения, администратору и бухгалтеру.');
        }

        $subdivisionsQuery = Subdivision::query()->active()->orderBy('name');
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

        $equipmentSearch = $this->equipmentBalancesSearchTerm($request);
        if ($equipmentSearch !== '') {
            $overviewTabQuery['equipment'] = $equipmentSearch;
        }

        $stockFilter = $this->overviewStockFilter($request);
        if ($stockFilter !== '') {
            $overviewTabQuery['stock_filter'] = $stockFilter;
        }

        $equipmentBalances = collect();
        $warehouseStockOptions = [];
        $canManageWarehouseStock = false;
        if ($selectedWarehouseId > 0 && $selectedWarehouse) {
            $equipmentBalancesQuery = $this->overviewWarehouseBalanceBaseQuery($selectedWarehouseId);
            $this->applyEquipmentBalancesSearchToMovementAggregates($equipmentBalancesQuery, $equipmentSearch);
            $this->applyOverviewBalanceStockFilter($equipmentBalancesQuery, $stockFilter);

            $equipmentBalances = $equipmentBalancesQuery
                ->orderBy('equipment.name')
                ->paginate($balancesPerPage['perPage'])
                ->appends($overviewTabQuery);

            $this->applyReserveEquipmentDisplayNamesToOverviewRows($equipmentBalances);

            if ($this->userCanManageStockOnWarehouse($user, $selectedWarehouse)) {
                $canManageWarehouseStock = true;
                $warehouseStockOptions = $this->mainWarehouseStockOperationOptions($selectedWarehouseId);
            }
        }

        return view('materials.overview', [
            'subdivisions' => $subdivisions,
            'selectedSubdivision' => $selectedSubdivision,
            'warehouses' => $warehouses,
            'selectedWarehouse' => $selectedWarehouse,
            'equipmentBalances' => $equipmentBalances,
            'usingDefaultMainWarehouse' => $usingDefaultMainWarehouse,
            'overviewTabQuery' => $overviewTabQuery,
            'equipmentSearch' => $equipmentSearch,
            'stockFilter' => $stockFilter,
            'canManageWarehouseStock' => $canManageWarehouseStock,
            'warehouseStockOptions' => $warehouseStockOptions,
            'mainWarehouse' => $mainWarehouse,
        ] + $balancesPerPage);
    }

    private function renderIndex(Request $request, bool $canManage): View
    {
        $warehouseFilter = $request->integer('warehouse_id');
        $selectedWarehouseId = $warehouseFilter > 0 ? $warehouseFilter : null;
        $mainWarehouse = $this->resolveMainWarehouse();

        $balancesPerPage = MaterialsListPerPage::fromRequest($request, 'balances');
        $equipmentSearch = $this->equipmentBalancesSearchTerm($request);

        $warehousesQuery = Warehouse::query()
            ->inActiveSubdivision()
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

        $this->applyEquipmentBalancesSearchToEquipmentQuery($materialsBalancesQuery, $equipmentSearch);

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
            'equipmentSearch',
        ) + $balancesPerPage);
    }

    private function equipmentBalancesSearchTerm(Request $request): string
    {
        return mb_substr(trim((string) $request->query('equipment', '')), 0, 150);
    }

    private function overviewStockFilter(Request $request): string
    {
        $filter = trim((string) $request->query('stock_filter', ''));

        return in_array($filter, [
            self::OVERVIEW_STOCK_FILTER_ON_WAREHOUSE,
            self::OVERVIEW_STOCK_FILTER_WRITTEN_OFF,
        ], true) ? $filter : '';
    }

    private function applyOverviewBalanceStockFilter(Builder $query, string $filter): void
    {
        if ($filter === '') {
            return;
        }

        $goodBucket = WarehouseStockBucket::GOOD;
        $defectiveBucket = WarehouseStockBucket::DEFECTIVE;
        $issueType = str_replace("'", "''", MaterialStockMovementType::NAME_ISSUE);

        $warehouseBalanceSql = "(
            SUM(CASE WHEN material_stock_movements.stock_bucket = {$goodBucket} AND msm_types.name = '{$issueType}' THEN -material_stock_movements.quantity WHEN material_stock_movements.stock_bucket = {$goodBucket} THEN material_stock_movements.quantity ELSE 0 END)
            + SUM(CASE WHEN material_stock_movements.stock_bucket = {$defectiveBucket} AND msm_types.name = '{$issueType}' THEN -material_stock_movements.quantity WHEN material_stock_movements.stock_bucket = {$defectiveBucket} THEN material_stock_movements.quantity ELSE 0 END)
        )";

        $qtyOutSql = WarehouseStockBucket::overviewWrittenOffQuantitySqlExpression();

        if ($filter === self::OVERVIEW_STOCK_FILTER_ON_WAREHOUSE) {
            $query->havingRaw("{$warehouseBalanceSql} > 0.0005");

            return;
        }

        $query->havingRaw("{$warehouseBalanceSql} <= 0.0005 AND {$qtyOutSql} > 0.0005");
    }

    private function applyEquipmentBalancesSearchToEquipmentQuery(Builder $query, string $term): void
    {
        $this->applyEquipmentBalancesSearchConstraints($query, $term, '');
    }

    private function applyEquipmentBalancesSearchToMovementAggregates(Builder $query, string $term): void
    {
        $this->applyEquipmentBalancesSearchConstraints($query, $term, 'equipment.');
    }

    private function applyEquipmentBalancesSearchConstraints(Builder $query, string $term, string $prefix): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $pattern = '%'.addcslashes($term, '%_\\').'%';
        $query->where($prefix.'name', 'like', $pattern);
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

        if (Equipment::catalogEntryExists($name, (string) $validated['measurement_type'])) {
            throw ValidationException::withMessages([
                'name' => 'Оборудование с таким названием и типом измерения уже есть в справочнике.',
            ]);
        }

        Equipment::query()->create([
            'name' => $name,
            'value' => null,
            'measurement_unit_id' => (int) $validated['measurement_unit_id'],
            'unit_type_id' => (int) $unit->unit_type_id,
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
                $balance = WarehouseStockBucket::balance(
                    (int) $validated['equipment_id'],
                    (int) $validated['warehouse_id'],
                    WarehouseStockBucket::GOOD
                );
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
                'stock_bucket' => WarehouseStockBucket::GOOD,
                'unit_price' => null,
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

    public function transferMainWarehouseToDefective(Request $request): RedirectResponse
    {
        $warehouse = $this->authorizeWarehouseStockManagement($request);

        $validated = $request->validate(
            $this->mainWarehouseStockOperationBaseRules() + [
                'defect_reason' => ['required', 'string', 'min:3', 'max:1000'],
            ],
            $this->mainWarehouseStockOperationValidationMessages() + [
                'defect_reason.required' => 'Укажите причину брака (не менее 3 символов).',
            ],
        );

        $operation = $this->resolveMainWarehouseStockOperation($validated, 'quantity', $warehouse);

        WarehouseStockBucket::transferGoodToDefectiveOnWarehouse(
            $operation['equipment_id'],
            (int) $warehouse->id,
            $operation['quantity'],
            (string) $validated['defect_reason'],
            (int) $request->user()->id,
            $operation['receipt_variant'],
        );

        return $this->redirectToOverviewAfterStockOperation($request, $warehouse)
            ->with('status', 'Оборудование переведено в брак на складе «'.$warehouse->name.'».');
    }

    public function disposeMainWarehouseDefective(Request $request): RedirectResponse
    {
        $warehouse = $this->authorizeWarehouseStockManagement($request);

        $validated = $request->validate(
            $this->mainWarehouseStockOperationBaseRules() + [
                'comment' => ['nullable', 'string', 'max:2000'],
            ],
            $this->mainWarehouseStockOperationValidationMessages(),
        );

        $operation = $this->resolveMainWarehouseStockOperation($validated, 'quantity', $warehouse);

        WarehouseStockBucket::disposeDefectiveOnWarehouse(
            $operation['equipment_id'],
            (int) $warehouse->id,
            $operation['quantity'],
            isset($validated['comment']) ? trim((string) $validated['comment']) : null,
            (int) $request->user()->id,
            $operation['receipt_variant'],
        );

        return $this->redirectToOverviewAfterStockOperation($request, $warehouse)
            ->with('status', 'Бракованное оборудование утилизировано со склада «'.$warehouse->name.'».');
    }

    private function authorizeMaterialsWarehouseSectionAccess(?User $user): void
    {
        if (! $user?->hasAnyRoleId(User::MATERIALS_WAREHOUSE_NAV_ROLE_IDS)) {
            abort(403, 'Раздел «Склады и оборудование» недоступен для вашей роли.');
        }
    }

    private function userCanManageStockOnWarehouse(?User $user, Warehouse $warehouse): bool
    {
        if ($user === null) {
            return false;
        }

        $mainWarehouse = $this->resolveMainWarehouse();
        if (
            $mainWarehouse
            && AdministrationWarehouse::isAdministrationWarehouse($warehouse)
            && (int) $warehouse->id === (int) $mainWarehouse->id
            && $user->hasAnyRoleId(User::MAIN_WAREHOUSE_STOCK_MANAGEMENT_ROLE_IDS)
        ) {
            return true;
        }

        if ($user->hasRoleId(User::FOREMAN_ROLE_ID)) {
            return $user->assignedSubdivisions()
                ->where('subdivisions.id', (int) $warehouse->subdivision_id)
                ->exists();
        }

        if ($user->hasRoleId(User::BOILER_CHIEF_ROLE_ID)) {
            return $user->boilerChiefSubdivisions()
                ->where('subdivisions.id', (int) $warehouse->subdivision_id)
                ->exists();
        }

        return false;
    }

    private function authorizeWarehouseStockManagement(Request $request): Warehouse
    {
        $warehouseId = $request->integer('warehouse_id');
        if ($warehouseId <= 0) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Выберите склад на странице обзора.',
            ]);
        }

        $warehouse = Warehouse::query()->find($warehouseId);
        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Выберите склад на странице обзора.',
            ]);
        }

        if (! $this->userCanManageStockOnWarehouse($request->user(), $warehouse)) {
            abort(403, 'Операции брака и утилизации на этом складе вам недоступны.');
        }

        return $warehouse;
    }

    /**
     * @return array<string, list<string>>
     */
    private function mainWarehouseStockOperationBaseRules(): array
    {
        return [
            'equipment_key' => ['required', 'string', 'max:64'],
            'quantity' => ['required', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mainWarehouseStockOperationValidationMessages(): array
    {
        return [
            'equipment_key.required' => 'Выберите оборудование в списке «Оборудование».',
            'equipment_key.string' => 'Выберите оборудование в списке «Оборудование».',
            'quantity.required' => 'Укажите количество.',
            'quantity.numeric' => 'Количество должно быть числом.',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{equipment_id: int, quantity: float, receipt_variant: ?string}
     */
    private function resolveMainWarehouseStockOperation(array $validated, string $quantityField, Warehouse $warehouse): array
    {
        $equipmentKey = trim((string) ($validated['equipment_key'] ?? ''));
        $option = collect($this->mainWarehouseStockOperationOptions((int) $warehouse->id))
            ->first(fn (array $row): bool => (string) $row['key'] === $equipmentKey);

        if ($option === null) {
            throw ValidationException::withMessages([
                'equipment_key' => 'Выберите оборудование в списке «Оборудование».',
            ]);
        }

        $equipment = Equipment::query()
            ->with(['measurementUnit.unitType:id,code'])
            ->findOrFail((int) $option['equipment_id']);

        $equipmentUnitType = (string) ($equipment->measurementUnit?->unitType?->code ?? '');
        $receiptVariant = isset($option['receipt_variant']) && $option['receipt_variant'] !== ''
            ? (string) $option['receipt_variant']
            : null;

        if ($equipmentUnitType === PieceQuantity::CLOTHING_MEASUREMENT_TYPE && $receiptVariant === null) {
            throw ValidationException::withMessages([
                'equipment_key' => 'Для одежды выберите позицию с указанным размером.',
            ]);
        }

        if ($equipmentUnitType !== PieceQuantity::CLOTHING_MEASUREMENT_TYPE) {
            $receiptVariant = null;
        }

        $qtyRaw = (string) ($validated[$quantityField] ?? '');
        if (in_array($equipmentUnitType, ['piece', 'mass', 'length'], true) && preg_match('/\p{L}/u', $qtyRaw)) {
            throw ValidationException::withMessages([
                $quantityField => 'Для оборудования с типом учёта «штуки», «масса» или «длина» в количестве не допускаются буквы — только число.',
            ]);
        }

        if (PieceQuantity::isPieceMeasurement($equipmentUnitType)) {
            PieceQuantity::assertWholeQuantity($validated[$quantityField]);
        }

        $quantity = PieceQuantity::isPieceMeasurement($equipmentUnitType)
            ? (float) PieceQuantity::normalizeStoredQuantity($validated[$quantityField], $equipmentUnitType)
            : (float) $validated[$quantityField];

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                $quantityField => 'Количество должно быть больше нуля.',
            ]);
        }

        return [
            'equipment_id' => (int) $equipment->id,
            'quantity' => $quantity,
            'receipt_variant' => $receiptVariant,
        ];
    }

    private function redirectToOverviewAfterStockOperation(Request $request, Warehouse $warehouse): RedirectResponse
    {
        return redirect()->route('materials.overview', array_filter([
            'subdivision_id' => (int) $warehouse->subdivision_id,
            'warehouse_id' => (int) $warehouse->id,
            'equipment' => $this->equipmentBalancesSearchTerm($request),
            'stock_filter' => $this->overviewStockFilter($request),
            'per_page' => $request->integer('per_page') ?: null,
        ], static fn ($value) => $value !== null && $value !== '' && $value !== 0));
    }

    /**
     * @return list<array{
     *     key: string,
     *     equipment_id: int,
     *     receipt_variant: ?string,
     *     label: string,
     *     good_balance: float,
     *     defective_balance: float,
     *     measurement_type_code: string,
     *     unit_code: string,
     * }>
     */
    private function mainWarehouseStockOperationOptions(int $warehouseId): array
    {
        return $this->overviewWarehouseBalanceBaseQuery($warehouseId)
            ->selectRaw('MAX(material_stock_movements.receipt_variant) as receipt_variant')
            ->orderBy('equipment.name')
            ->get()
            ->map(function ($row): ?array {
                $goodBalance = (float) ($row->balance ?? 0);
                $defectiveBalance = (float) ($row->defective_balance ?? 0);
                if ($goodBalance < 0.0005 && $defectiveBalance < 0.0005) {
                    return null;
                }

                $unitCode = trim((string) ($row->unit_code ?? '')) ?: 'шт';
                $measurementTypeCode = trim((string) ($row->measurement_type_code ?? ''));
                $receiptVariant = trim((string) ($row->receipt_variant ?? ''));
                $receiptVariant = $receiptVariant !== '' ? $receiptVariant : null;
                $equipmentName = ReserveEquipmentDisplayName::resolve(
                    (string) ($row->equipment_name ?? ''),
                    (int) ($row->equipment_id ?? 0) ?: null
                );
                $label = $equipmentName.' ('.$unitCode.')';
                if ($measurementTypeCode === PieceQuantity::CLOTHING_MEASUREMENT_TYPE && $receiptVariant !== null) {
                    $label = $equipmentName.', размер '.$receiptVariant;
                }

                $key = (int) $row->equipment_id.':'.($receiptVariant ?? '');

                return [
                    'key' => $key,
                    'equipment_id' => (int) $row->equipment_id,
                    'receipt_variant' => $receiptVariant,
                    'label' => $label,
                    'good_balance' => $goodBalance,
                    'defective_balance' => $defectiveBalance,
                    'measurement_type_code' => $measurementTypeCode,
                    'unit_code' => $unitCode,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function overviewWarehouseBalanceBaseQuery(int $warehouseId): \Illuminate\Database\Eloquent\Builder
    {
        return $this->warehouseBalanceAggregatesQuery()
            ->where('material_stock_movements.warehouse_id', $warehouseId);
    }

    private function applyReserveEquipmentDisplayNamesToOverviewRows(\Illuminate\Contracts\Pagination\Paginator $paginator): void
    {
        $repairedEquipmentIds = [];
        foreach ($paginator as $row) {
            $equipmentId = (int) ($row->equipment_id ?? 0);
            $storedName = (string) ($row->equipment_name ?? '');
            if ($equipmentId <= 0 || ! ReserveEquipmentDisplayName::isReserveEquipmentName($storedName)) {
                continue;
            }

            if (! isset($repairedEquipmentIds[$equipmentId])) {
                $equipment = Equipment::query()->find($equipmentId);
                if ($equipment !== null) {
                    ReserveEquipmentDisplayName::repairEquipmentRecord($equipment);
                    $storedName = (string) $equipment->fresh()->name;
                }
                $repairedEquipmentIds[$equipmentId] = true;
            }

            $row->equipment_name = ReserveEquipmentDisplayName::resolve($storedName, $equipmentId);
        }
    }
    private function warehouseBalanceAggregatesQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $r = MaterialStockMovementType::NAME_RECEIPT;
        $i = MaterialStockMovementType::NAME_ISSUE;
        $clothingType = PieceQuantity::CLOTHING_MEASUREMENT_TYPE;
        $goodBucket = WarehouseStockBucket::GOOD;
        $defectiveBucket = WarehouseStockBucket::DEFECTIVE;

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
            ->selectRaw("SUM(CASE WHEN material_stock_movements.stock_bucket = {$goodBucket} AND msm_types.name = ? THEN material_stock_movements.quantity ELSE 0 END) as qty_in", [$r])
            ->selectRaw(WarehouseStockBucket::overviewWrittenOffQuantitySqlExpression().' as qty_out')
            ->selectRaw("SUM(CASE WHEN material_stock_movements.stock_bucket = {$goodBucket} AND msm_types.name = ? THEN -material_stock_movements.quantity WHEN material_stock_movements.stock_bucket = {$goodBucket} THEN material_stock_movements.quantity ELSE 0 END) as balance", [$i])
            ->selectRaw("SUM(CASE WHEN material_stock_movements.stock_bucket = {$defectiveBucket} AND msm_types.name = ? THEN -material_stock_movements.quantity WHEN material_stock_movements.stock_bucket = {$defectiveBucket} THEN material_stock_movements.quantity ELSE 0 END) as defective_balance", [$i]);
    }
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
        return WarehouseStockBucket::balance($equipmentId, $warehouseId, WarehouseStockBucket::GOOD);
    }

    private function resolveMainWarehouse(): ?Warehouse
    {
        return AdministrationWarehouse::resolvePrimaryWarehouse();
    }
    private function clothingCatalogSizeOptions(): array
    {
        return ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '4XL', '5XL'];
    }
}
