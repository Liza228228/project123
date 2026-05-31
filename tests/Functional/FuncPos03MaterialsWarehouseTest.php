<?php

// функциональный тест
use App\Models\Equipment;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\MeasurementUnit;
use App\Models\Subdivision;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\AdministrationWarehouse;
use App\Support\WarehouseStockBucket;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('supply head can add equipment and post receipt to administration warehouse', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $mainWarehouse = FunctionalScenarioFixture::primaryAdministrationWarehouse();

    $supplyHead = User::query()->create([
        'surname' => 'Петров',
        'name' => 'Николай',
        'patronymic' => 'Андреевич',
        'email' => 'Petrov-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $meterUnitId = (int) MeasurementUnit::query()
        ->where('code', 'м')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'length'))
        ->value('id');

    $uniqueName = 'Заглушка DN50 '.uniqid();

    $catalogResponse = $this->actingAs($supplyHead)->post(route('materials.store-material'), [
        'name' => $uniqueName,
        'measurement_type' => 'length',
        'measurement_unit_id' => $meterUnitId,
    ]);

    $catalogResponse->assertRedirect(route('materials.index'));
    $catalogResponse->assertSessionHas('status', 'Оборудование добавлено в справочник.');

    $equipmentId = (int) Equipment::query()->where('name', $uniqueName)->value('id');
    expect($equipmentId)->toBeGreaterThan(0);
    expect(Equipment::query()->where('id', $equipmentId)->value('value'))->toBeNull();

    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);

    $movementResponse = $this->actingAs($supplyHead)->post(route('materials.store-movement'), [
        'equipment_id' => $equipmentId,
        'warehouse_id' => $mainWarehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 100,
    ]);

    $movementResponse->assertRedirect();
    $movementResponse->assertSessionHas('status', 'Операция по оборудованию сохранена.');
});

test('supply head can add catalog equipment with clothing size from list', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $supplyHead = User::query()->create([
        'surname' => 'Сидоров',
        'name' => 'Иван',
        'patronymic' => 'Петрович',
        'email' => 'Sidorov-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $clothingUnitId = (int) MeasurementUnit::query()
        ->whereHas('unitType', fn ($q) => $q->where('code', 'clothing_size'))
        ->value('id');

    expect($clothingUnitId)->toBeGreaterThan(0);

    $uniqueName = 'Куртка рабочая '.uniqid();

    $catalogResponse = $this->actingAs($supplyHead)->post(route('materials.store-material'), [
        'name' => $uniqueName,
        'measurement_type' => 'clothing_size',
        'measurement_unit_id' => $clothingUnitId,
    ]);

    $catalogResponse->assertRedirect(route('materials.index'));
    $catalogResponse->assertSessionHas('status', 'Оборудование добавлено в справочник.');

    expect(Equipment::query()->where('name', $uniqueName)->value('value'))->toBeNull();
});

test('receipt quantity rejects letters for length-type equipment', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $mainWarehouse = FunctionalScenarioFixture::primaryAdministrationWarehouse();

    $supplyHead = User::query()->create([
        'surname' => 'Орлов',
        'name' => 'Пётр',
        'patronymic' => 'Сергеевич',
        'email' => 'Orlov-qty-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $meterUnitId = (int) MeasurementUnit::query()
        ->where('code', 'м')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'length'))
        ->value('id');

    $equipmentName = 'Труба для кол-ва '.uniqid();
    $this->actingAs($supplyHead)->post(route('materials.store-material'), [
        'name' => $equipmentName,
        'measurement_type' => 'length',
        'measurement_unit_id' => $meterUnitId,
    ])->assertRedirect(route('materials.index'));

    $equipmentId = (int) Equipment::query()->where('name', $equipmentName)->value('id');
    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);

    $response = $this->actingAs($supplyHead)->from(route('materials.index'))->post(route('materials.store-movement'), [
        'equipment_id' => $equipmentId,
        'warehouse_id' => $mainWarehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => '10a',
    ]);

    $response->assertSessionHasErrors('quantity');
});

test('receipt quantity rejects fractional amount for piece-type equipment', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $mainWarehouse = FunctionalScenarioFixture::primaryAdministrationWarehouse();

    $supplyHead = User::query()->create([
        'surname' => 'Кузнецов',
        'name' => 'Алексей',
        'patronymic' => 'Игоревич',
        'email' => 'Kuznetsov-piece-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $pieceUnitId = (int) MeasurementUnit::query()
        ->whereHas('unitType', fn ($q) => $q->where('code', 'piece'))
        ->value('id');

    $equipmentName = 'Клапан для штук '.uniqid();
    $this->actingAs($supplyHead)->post(route('materials.store-material'), [
        'name' => $equipmentName,
        'measurement_type' => 'piece',
        'measurement_unit_id' => $pieceUnitId,
    ])->assertRedirect(route('materials.index'));

    $equipmentId = (int) Equipment::query()->where('name', $equipmentName)->value('id');
    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);

    $response = $this->actingAs($supplyHead)->from(route('materials.index'))->post(route('materials.store-movement'), [
        'equipment_id' => $equipmentId,
        'warehouse_id' => $mainWarehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => '2.5',
    ]);

    $response->assertSessionHasErrors('quantity');
});

test('duplicate catalog equipment name shows справочник message', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $supplyHead = User::query()->create([
        'surname' => 'Иванов',
        'name' => 'Сергей',
        'patronymic' => 'Иванович',
        'email' => 'Ivanov-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $pieceUnitId = (int) MeasurementUnit::query()
        ->whereHas('unitType', fn ($q) => $q->where('code', 'piece'))
        ->value('id');

    $name = 'Дубликат каталога '.uniqid();

    $this->actingAs($supplyHead)
        ->from(route('materials.index'))
        ->post(route('materials.store-material'), [
            'name' => $name,
            'measurement_type' => 'piece',
            'measurement_unit_id' => $pieceUnitId,
        ])
        ->assertRedirect(route('materials.index'));

    $dup = $this->actingAs($supplyHead)
        ->from(route('materials.index'))
        ->post(route('materials.store-material'), [
            'name' => $name,
            'measurement_type' => 'piece',
            'measurement_unit_id' => $pieceUnitId,
        ]);

    $dup->assertSessionHasErrors([
        'name' => 'Оборудование с таким названием и типом измерения уже есть в справочнике.',
    ]);
});

test('catalog allows same equipment name with different measurement type', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $supplyHead = User::query()->create([
        'surname' => 'Петров',
        'name' => 'Сергей',
        'patronymic' => 'Иванович',
        'email' => 'Ivanov-types-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $pieceUnitId = (int) MeasurementUnit::query()
        ->whereHas('unitType', fn ($q) => $q->where('code', 'piece'))
        ->value('id');
    $massUnitId = (int) MeasurementUnit::query()
        ->where('code', 'кг')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'mass'))
        ->value('id');

    $name = 'Гвозди тест '.uniqid();

    $this->actingAs($supplyHead)
        ->post(route('materials.store-material'), [
            'name' => $name,
            'measurement_type' => 'piece',
            'measurement_unit_id' => $pieceUnitId,
        ])
        ->assertRedirect(route('materials.index'));

    $this->actingAs($supplyHead)
        ->post(route('materials.store-material'), [
            'name' => $name,
            'measurement_type' => 'mass',
            'measurement_unit_id' => $massUnitId,
        ])
        ->assertRedirect(route('materials.index'))
        ->assertSessionHas('status', 'Оборудование добавлено в справочник.');

    $normalizedName = mb_strtolower(trim($name));
    $matchingCatalogNames = Equipment::query()
        ->where('is_catalog', true)
        ->pluck('name')
        ->filter(fn (string $storedName) => mb_strtolower(trim($storedName)) === $normalizedName);

    expect($matchingCatalogNames->count())->toBe(2);
});

test('clothing catalog receipt saves size in receipt_variant and quantity one', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $mainWarehouse = FunctionalScenarioFixture::primaryAdministrationWarehouse();

    $supplyHead = User::query()->create([
        'surname' => 'Козлов',
        'name' => 'Алексей',
        'patronymic' => 'Иванович',
        'email' => 'Kozlov-rcpt-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $clothingUnitId = (int) MeasurementUnit::query()
        ->whereHas('unitType', fn ($q) => $q->where('code', 'clothing_size'))
        ->value('id');

    $name = 'Спецодежда тест '.uniqid();
    $this->actingAs($supplyHead)->post(route('materials.store-material'), [
        'name' => $name,
        'measurement_type' => 'clothing_size',
        'measurement_unit_id' => $clothingUnitId,
    ])->assertRedirect(route('materials.index'));

    $equipmentId = (int) Equipment::query()->where('name', $name)->value('id');
    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);

    $this->actingAs($supplyHead)->from(route('materials.index'))->post(route('materials.store-movement'), [
        'equipment_id' => $equipmentId,
        'warehouse_id' => $mainWarehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'receipt_variant' => 'L',
    ])->assertRedirect(route('materials.index', ['warehouse_id' => $mainWarehouse->id]));

    $row = MaterialStockMovement::query()
        ->where('equipment_id', $equipmentId)
        ->orderByDesc('id')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->receipt_variant)->toBe('L');
    expect((float) $row->quantity)->toBe(1.0);
});

test('technical director can access supply procurement sections but not equipment accounting', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $td = User::query()->create([
        'surname' => 'Техдир',
        'name' => 'Закупка',
        'patronymic' => 'Тестович',
        'email' => 'td-procurement-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 6,
    ]);

    $this->actingAs($td)
        ->get(route('applications.custom-equipment-to-order'))
        ->assertForbidden();

    $this->actingAs($td)
        ->get(route('applications.commercial-offer-procurement'))
        ->assertNotFound();

    $this->actingAs($td)->get(route('materials.index'))->assertForbidden();
});

test('technical director cannot open equipment accounting or post warehouse catalog operations', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $mainWarehouse = FunctionalScenarioFixture::primaryAdministrationWarehouse();

    $td = User::query()->create([
        'surname' => 'Техдир',
        'name' => 'Тест',
        'patronymic' => 'Тестович',
        'email' => 'td-mat-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 6,
    ]);

    $this->actingAs($td)->get(route('materials.index'))->assertForbidden();

    $meterUnitId = (int) MeasurementUnit::query()
        ->where('code', 'м')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'length'))
        ->value('id');

    $this->actingAs($td)->post(route('materials.store-material'), [
        'name' => 'Оборудование ТД '.uniqid(),
        'measurement_type' => 'length',
        'measurement_unit_id' => $meterUnitId,
    ])->assertForbidden();

    $pieceUnitId = (int) MeasurementUnit::query()
        ->where('code', 'шт')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'piece'))
        ->value('id');
    $equipment = Equipment::query()->create([
        'name' => 'Единица для ТД '.uniqid(),
        'value' => null,
        'measurement_unit_id' => $pieceUnitId,
        'is_catalog' => true,
    ]);
    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);

    $this->actingAs($td)->post(route('materials.store-movement'), [
        'equipment_id' => $equipment->id,
        'warehouse_id' => $mainWarehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 1,
    ])->assertForbidden();

    $this->actingAs($td)->get(route('materials.overview'))->assertOk();
    $this->actingAs($td)->get(route('materials.movements'))->assertOk();
});

test('materials balances filter equipment by search term', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $supplyHead = User::query()->create([
        'surname' => 'Фильтр',
        'name' => 'Остатки',
        'patronymic' => 'Тест',
        'email' => 'balances-filter-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $pieceUnitId = (int) MeasurementUnit::query()
        ->whereHas('unitType', fn ($q) => $q->where('code', 'piece'))
        ->value('id');

    Equipment::query()->create([
        'name' => 'Уникальные гвозди фильтр',
        'value' => null,
        'measurement_unit_id' => $pieceUnitId,
        'unit_type_id' => (int) MeasurementUnit::query()->whereKey($pieceUnitId)->value('unit_type_id'),
        'is_catalog' => true,
    ]);
    Equipment::query()->create([
        'name' => 'Другая труба фильтр',
        'value' => null,
        'measurement_unit_id' => $pieceUnitId,
        'unit_type_id' => (int) MeasurementUnit::query()->whereKey($pieceUnitId)->value('unit_type_id'),
        'is_catalog' => true,
    ]);

    $this->actingAs($supplyHead)
        ->get(route('materials.index', ['equipment' => 'гвозди фильтр']))
        ->assertOk()
        ->assertSee('Уникальные гвозди фильтр', false)
        ->assertDontSee('Другая труба фильтр', false);
});

test('accountant can view administration warehouse balances in overview', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $subdivision = Subdivision::query()->firstOrCreate(
        ['name' => AdministrationWarehouse::SUBDIVISION_NAME],
    );

    Warehouse::query()->where('subdivision_id', $subdivision->id)->update(['is_primary' => false]);

    $warehouse = Warehouse::query()->firstOrCreate(
        [
            'subdivision_id' => $subdivision->id,
            'name' => AdministrationWarehouse::WAREHOUSE_NAME,
        ],
        ['is_primary' => true],
    );
    $warehouse->update(['is_primary' => true]);

    $accountant = User::query()->create([
        'surname' => 'Бухгалтер',
        'name' => 'Остатки',
        'patronymic' => 'Складович',
        'email' => 'accountant-overview-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ACCOUNTANT_ROLE_ID,
    ]);

    $this->actingAs($accountant)
        ->get(route('materials.overview', [
            'subdivision_id' => $subdivision->id,
            'warehouse_id' => $warehouse->id,
        ]))
        ->assertOk()
        ->assertSee(AdministrationWarehouse::WAREHOUSE_NAME, false);

    $this->actingAs($accountant)
        ->get(route('materials.overview'))
        ->assertOk();
});

test('warehouse overview filters equipment balances by search term', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $subdivision = Subdivision::query()->where('name', AdministrationWarehouse::SUBDIVISION_NAME)->firstOrFail();
    $warehouse = Warehouse::query()->where('subdivision_id', $subdivision->id)->firstOrFail();
    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
    $meterUnitId = (int) MeasurementUnit::query()
        ->where('code', 'м')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'length'))
        ->value('id');

    $needleEquipment = Equipment::query()->create([
        'name' => 'Компенсатор сильфонный '.uniqid(),
        'measurement_unit_id' => $meterUnitId,
        'unit_type_id' => MeasurementUnit::query()->whereKey($meterUnitId)->value('unit_type_id'),
        'is_catalog' => true,
    ]);

    $otherEquipment = Equipment::query()->create([
        'name' => 'Тепловая камера '.uniqid(),
        'measurement_unit_id' => $meterUnitId,
        'unit_type_id' => MeasurementUnit::query()->whereKey($meterUnitId)->value('unit_type_id'),
        'is_catalog' => true,
    ]);

    foreach ([$needleEquipment, $otherEquipment] as $equipment) {
        MaterialStockMovement::query()->create([
            'equipment_id' => $equipment->id,
            'warehouse_id' => $warehouse->id,
            'material_stock_movement_type_id' => $receiptTypeId,
            'quantity' => 5,
            'comment' => 'Тестовый приход для поиска.',
        ]);
    }

    $accountant = User::query()->create([
        'surname' => 'Бухгалтер',
        'name' => 'Поиск',
        'patronymic' => 'Остатков',
        'email' => 'accountant-overview-search-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ACCOUNTANT_ROLE_ID,
    ]);

    $this->actingAs($accountant)
        ->get(route('materials.overview', [
            'subdivision_id' => $subdivision->id,
            'warehouse_id' => $warehouse->id,
            'equipment' => 'Компенсатор сильфонный',
        ]))
        ->assertOk()
        ->assertSee($needleEquipment->name, false)
        ->assertDontSee($otherEquipment->name, false);
});

test('warehouse overview filters equipment balances by stock presence', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $subdivision = Subdivision::query()->where('name', AdministrationWarehouse::SUBDIVISION_NAME)->firstOrFail();
    $warehouse = Warehouse::query()->where('subdivision_id', $subdivision->id)->firstOrFail();
    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
    $issueTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
    $meterUnitId = (int) MeasurementUnit::query()
        ->where('code', 'м')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'length'))
        ->value('id');

    $onStockEquipment = Equipment::query()->create([
        'name' => 'Остаток на складе '.uniqid(),
        'measurement_unit_id' => $meterUnitId,
        'unit_type_id' => MeasurementUnit::query()->whereKey($meterUnitId)->value('unit_type_id'),
        'is_catalog' => true,
    ]);

    $writtenOffEquipment = Equipment::query()->create([
        'name' => 'Полностью списано '.uniqid(),
        'measurement_unit_id' => $meterUnitId,
        'unit_type_id' => MeasurementUnit::query()->whereKey($meterUnitId)->value('unit_type_id'),
        'is_catalog' => true,
    ]);

    MaterialStockMovement::query()->create([
        'equipment_id' => $onStockEquipment->id,
        'warehouse_id' => $warehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 10,
        'comment' => 'Приход для фильтра «на складе».',
    ]);

    MaterialStockMovement::query()->create([
        'equipment_id' => $writtenOffEquipment->id,
        'warehouse_id' => $warehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 8,
        'comment' => 'Приход перед списанием.',
    ]);
    MaterialStockMovement::query()->create([
        'equipment_id' => $writtenOffEquipment->id,
        'warehouse_id' => $warehouse->id,
        'material_stock_movement_type_id' => $issueTypeId,
        'quantity' => 8,
        'comment' => 'Полное списание.',
    ]);

    $accountant = User::query()->create([
        'surname' => 'Бухгалтер',
        'name' => 'Фильтр',
        'patronymic' => 'Остатков',
        'email' => 'accountant-overview-stock-filter-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ACCOUNTANT_ROLE_ID,
    ]);

    $overviewParams = [
        'subdivision_id' => $subdivision->id,
        'warehouse_id' => $warehouse->id,
    ];

    $this->actingAs($accountant)
        ->get(route('materials.overview', $overviewParams + ['stock_filter' => 'on_stock']))
        ->assertOk()
        ->assertSee($onStockEquipment->name, false)
        ->assertDontSee($writtenOffEquipment->name, false);

    $this->actingAs($accountant)
        ->get(route('materials.overview', $overviewParams + ['stock_filter' => 'written_off']))
        ->assertOk()
        ->assertSee($writtenOffEquipment->name, false)
        ->assertDontSee($onStockEquipment->name, false);
});

test('management can transfer to defect issue and dispose stock on main warehouse overview', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $subdivision = Subdivision::query()->firstOrCreate(
        ['name' => AdministrationWarehouse::SUBDIVISION_NAME],
    );
    Warehouse::query()->where('subdivision_id', $subdivision->id)->update(['is_primary' => false]);
    $mainWarehouse = Warehouse::query()->firstOrCreate(
        [
            'subdivision_id' => $subdivision->id,
            'name' => AdministrationWarehouse::WAREHOUSE_NAME,
        ],
        ['is_primary' => true],
    );
    $mainWarehouse->update(['is_primary' => true]);

    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
    $meterUnitId = (int) MeasurementUnit::query()
        ->where('code', 'м')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'length'))
        ->value('id');

    $equipment = Equipment::query()->create([
        'name' => 'Кран шаровой '.uniqid(),
        'measurement_unit_id' => $meterUnitId,
        'unit_type_id' => MeasurementUnit::query()->whereKey($meterUnitId)->value('unit_type_id'),
        'is_catalog' => true,
    ]);

    MaterialStockMovement::query()->create([
        'equipment_id' => $equipment->id,
        'warehouse_id' => $mainWarehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 10,
        'comment' => 'Приход для операций основного склада.',
    ]);

    $administrator = User::query()->create([
        'surname' => 'Админ',
        'name' => 'Склад',
        'patronymic' => 'Основной',
        'email' => 'admin-main-wh-stock-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ADMINISTRATOR_ROLE_ID,
    ]);

    $overviewParams = [
        'subdivision_id' => $mainWarehouse->subdivision_id,
        'warehouse_id' => $mainWarehouse->id,
    ];

    $this->actingAs($administrator)
        ->get(route('materials.overview', $overviewParams))
        ->assertOk()
        ->assertSee('Брак и списание на складе', false)
        ->assertSee($equipment->name, false);

    $this->actingAs($administrator)
        ->post(route('materials.overview-transfer-defective'), $overviewParams + [
            'equipment_key' => $equipment->id.':',
            'quantity' => 3,
            'defect_reason' => 'Коррозия корпуса',
        ])
        ->assertRedirect(route('materials.overview', $overviewParams))
        ->assertSessionHas('status');

    expect(WarehouseStockBucket::balance($equipment->id, $mainWarehouse->id, WarehouseStockBucket::GOOD))->toBe(7.0);
    expect(WarehouseStockBucket::balance($equipment->id, $mainWarehouse->id, WarehouseStockBucket::DEFECTIVE))->toBe(3.0);

    $this->actingAs($administrator)
        ->post(route('materials.overview-dispose-defective'), $overviewParams + [
            'equipment_key' => $equipment->id.':',
            'quantity' => 3,
            'comment' => 'Утилизация брака',
        ])
        ->assertRedirect(route('materials.overview', $overviewParams))
        ->assertSessionHas('status');

    expect(WarehouseStockBucket::balance($equipment->id, $mainWarehouse->id, WarehouseStockBucket::DEFECTIVE))->toBe(0.0);
});

test('foreman and boiler chief can manage defect stock on assigned subdivision warehouse overview', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $subdivision = Subdivision::query()->create(['name' => 'Участок брака '.uniqid()]);
    $warehouse = Warehouse::query()->create([
        'name' => 'Склад участка '.uniqid(),
        'subdivision_id' => $subdivision->id,
        'is_primary' => true,
    ]);

    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
    $meterUnitId = (int) MeasurementUnit::query()
        ->where('code', 'м')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'length'))
        ->value('id');

    $equipment = Equipment::query()->create([
        'name' => 'Насос для брака '.uniqid(),
        'measurement_unit_id' => $meterUnitId,
        'unit_type_id' => MeasurementUnit::query()->whereKey($meterUnitId)->value('unit_type_id'),
        'is_catalog' => true,
    ]);

    MaterialStockMovement::query()->create([
        'equipment_id' => $equipment->id,
        'warehouse_id' => $warehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 8,
        'comment' => 'Приход для операций склада участка.',
    ]);

    $foreman = User::query()->create([
        'surname' => 'Мастер',
        'name' => 'Участка',
        'patronymic' => 'Тест',
        'email' => 'foreman-wh-stock-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::FOREMAN_ROLE_ID,
    ]);
    $foreman->assignedSubdivisions()->sync([$subdivision->id]);

    $boilerChief = User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Котельной',
        'patronymic' => 'Тест',
        'email' => 'chief-wh-stock-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::BOILER_CHIEF_ROLE_ID,
    ]);
    $boilerChief->boilerChiefSubdivisions()->sync([$subdivision->id]);

    $overviewParams = [
        'subdivision_id' => $subdivision->id,
        'warehouse_id' => $warehouse->id,
    ];

    foreach ([$foreman, $boilerChief] as $actor) {
        $this->actingAs($actor)
            ->get(route('materials.overview', $overviewParams))
            ->assertOk()
            ->assertSee('Брак и списание на складе', false)
            ->assertSee($equipment->name, false);

        $this->actingAs($actor)
            ->post(route('materials.overview-transfer-defective'), $overviewParams + [
                'equipment_key' => $equipment->id.':',
                'quantity' => 2,
                'defect_reason' => 'Повреждение при эксплуатации',
            ])
            ->assertRedirect(route('materials.overview', $overviewParams))
            ->assertSessionHas('status');
    }

    expect(WarehouseStockBucket::balance($equipment->id, $warehouse->id, WarehouseStockBucket::GOOD))->toBe(4.0);
    expect(WarehouseStockBucket::balance($equipment->id, $warehouse->id, WarehouseStockBucket::DEFECTIVE))->toBe(4.0);

    $this->actingAs($foreman)
        ->post(route('materials.overview-dispose-defective'), $overviewParams + [
            'equipment_key' => $equipment->id.':',
            'quantity' => 4,
            'comment' => 'Утилизация',
        ])
        ->assertRedirect(route('materials.overview', $overviewParams))
        ->assertSessionHas('status');

    expect(WarehouseStockBucket::balance($equipment->id, $warehouse->id, WarehouseStockBucket::DEFECTIVE))->toBe(0.0);
});

test('foreman cannot manage defect stock on main administration warehouse', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $subdivision = Subdivision::query()->firstOrCreate(
        ['name' => AdministrationWarehouse::SUBDIVISION_NAME],
    );
    Warehouse::query()->where('subdivision_id', $subdivision->id)->update(['is_primary' => false]);
    $mainWarehouse = Warehouse::query()->firstOrCreate(
        [
            'subdivision_id' => $subdivision->id,
            'name' => AdministrationWarehouse::WAREHOUSE_NAME,
        ],
        ['is_primary' => true],
    );
    $mainWarehouse->update(['is_primary' => true]);

    $otherSubdivision = Subdivision::query()->create(['name' => 'Участок без админки '.uniqid()]);
    $foreman = User::query()->create([
        'surname' => 'Мастер',
        'name' => 'Без',
        'patronymic' => 'Админки',
        'email' => 'foreman-no-admin-wh-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::FOREMAN_ROLE_ID,
    ]);
    $foreman->assignedSubdivisions()->sync([$otherSubdivision->id]);

    $overviewParams = [
        'subdivision_id' => $mainWarehouse->subdivision_id,
        'warehouse_id' => $mainWarehouse->id,
    ];

    $this->actingAs($foreman)
        ->get(route('materials.overview', $overviewParams))
        ->assertForbidden();

    $this->actingAs($foreman)
        ->post(route('materials.overview-transfer-defective'), $overviewParams + [
            'equipment_key' => '1:',
            'quantity' => 1,
            'defect_reason' => 'Тест',
        ])
        ->assertForbidden();
});

test('main warehouse stock operations reject equipment that is not on main warehouse', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $subdivision = Subdivision::query()->firstOrCreate(
        ['name' => AdministrationWarehouse::SUBDIVISION_NAME],
    );
    Warehouse::query()->where('subdivision_id', $subdivision->id)->update(['is_primary' => false]);
    $mainWarehouse = Warehouse::query()->firstOrCreate(
        [
            'subdivision_id' => $subdivision->id,
            'name' => AdministrationWarehouse::WAREHOUSE_NAME,
        ],
        ['is_primary' => true],
    );
    $mainWarehouse->update(['is_primary' => true]);

    $otherSubdivision = Subdivision::query()->create(['name' => 'Участок для теста списания '.uniqid()]);
    $otherWarehouse = Warehouse::query()->create([
        'name' => 'Склад участка '.uniqid(),
        'subdivision_id' => $otherSubdivision->id,
        'is_primary' => false,
    ]);

    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
    $meterUnitId = (int) MeasurementUnit::query()
        ->where('code', 'м')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'length'))
        ->value('id');

    $equipment = Equipment::query()->create([
        'name' => 'Только на участке '.uniqid(),
        'measurement_unit_id' => $meterUnitId,
        'unit_type_id' => MeasurementUnit::query()->whereKey($meterUnitId)->value('unit_type_id'),
        'is_catalog' => true,
    ]);

    MaterialStockMovement::query()->create([
        'equipment_id' => $equipment->id,
        'warehouse_id' => $otherWarehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 5,
        'comment' => 'Приход только на склад участка.',
    ]);

    $director = User::query()->create([
        'surname' => 'Директор',
        'name' => 'Склад',
        'patronymic' => 'Тест',
        'email' => 'director-main-wh-stock-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    $this->actingAs($director)
        ->post(route('materials.overview-transfer-defective'), [
            'subdivision_id' => $mainWarehouse->subdivision_id,
            'warehouse_id' => $mainWarehouse->id,
            'equipment_key' => $equipment->id.':',
            'quantity' => 1,
            'defect_reason' => 'Тестовая причина',
        ])
        ->assertSessionHasErrors('equipment_key');
});
