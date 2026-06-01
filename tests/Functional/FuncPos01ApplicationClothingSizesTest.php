<?php

// функциональный тест
use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\Equipment;
use App\Models\MeasurementUnit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('application store allows multiple size-type catalog lines with different sizes and rejects duplicate size', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Спецодежда тест');

    $sizeUnitId = (int) MeasurementUnit::query()
        ->where('code', 'разм')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'clothing_size'))
        ->value('id');
    expect($sizeUnitId)->toBeGreaterThan(0);

    $boots = Equipment::query()->create([
        'name' => 'Ботинки рабочие',
        'value' => null,
        'measurement_unit_id' => $sizeUnitId,
        'is_catalog' => true,
    ]);

    $date = now()->addDays(7)->format('Y-m-d');

    $ok = $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => $date,
        'items' => [
            [
                'equipment_id' => $boots->id,
                'quantity' => 2,
                'measurement_type' => 'clothing_size',
                'quantity_unit' => 'разм',
                'size_value' => 'M',
            ],
            [
                'equipment_id' => $boots->id,
                'quantity' => 4,
                'measurement_type' => 'clothing_size',
                'quantity_unit' => 'разм',
                'size_value' => 'L',
            ],
        ],
    ]);
    $ok->assertRedirect(route('applications.index'));

    $application = Application::query()->first();
    expect($application)->not->toBeNull();
    $items = ApplicationItem::query()->where('application_id', $application->id)->get();
    expect($items)->toHaveCount(2);
    expect($items->pluck('size_value')->sort()->values()->all())->toBe(['L', 'M']);
    expect($items->firstWhere('size_value', 'M')->quantity)->toBe(2);
    expect($items->firstWhere('size_value', 'L')->quantity)->toBe(4);

    $dup = $this->actingAs($ctx['foreman'])->from(route('applications.create'))->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => $date,
        'items' => [
            [
                'equipment_id' => $boots->id,
                'quantity' => 1,
                'measurement_type' => 'clothing_size',
                'quantity_unit' => 'разм',
                'size_value' => 'M',
            ],
            [
                'equipment_id' => $boots->id,
                'quantity' => 1,
                'measurement_type' => 'clothing_size',
                'quantity_unit' => 'разм',
                'size_value' => 'M',
            ],
        ],
    ]);
    $dup->assertSessionHasErrors('equipment');
    expect(Application::query()->count())->toBe(1);
});

test('application store rejects fractional quantity for clothing_size lines', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Спецодежда дробь');

    $sizeUnitId = (int) MeasurementUnit::query()
        ->where('code', 'разм')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'clothing_size'))
        ->value('id');
    expect($sizeUnitId)->toBeGreaterThan(0);

    $jacket = Equipment::query()->create([
        'name' => 'Куртка рабочая',
        'value' => null,
        'measurement_unit_id' => $sizeUnitId,
        'is_catalog' => true,
    ]);

    $response = $this->actingAs($ctx['foreman'])->from(route('applications.create'))->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $jacket->id,
                'quantity' => '2.5',
                'measurement_type' => 'clothing_size',
                'quantity_unit' => 'разм',
                'size_value' => 'L',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.quantity');
    expect(Application::query()->count())->toBe(0);
});

test('custom clothing line keeps multi-word free-text equipment name', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Спецодежда название');

    $response = $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_name' => 'спец одежда',
                'quantity' => 8,
                'measurement_type' => 'clothing_size',
                'quantity_unit' => 'разм',
                'size_value' => 'M',
            ],
        ],
    ]);
    $response->assertRedirect(route('applications.index'));

    $item = ApplicationItem::query()->first();
    expect($item)->not->toBeNull();
    expect($item->raw_input)->toBe('спец одежда');
    expect($item->equipment_name)->toBe('спец одежда');
    expect($item->size_value)->toBe('M');
    expect($item->equipment_display_name)->toBe('спец одежда');
    expect($item->quantity_with_unit)->toBe('8×M');
});

test('application store rejects zero quantity for clothing_size lines', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Спецодежда ноль');

    $sizeUnitId = (int) MeasurementUnit::query()
        ->where('code', 'разм')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'clothing_size'))
        ->value('id');
    expect($sizeUnitId)->toBeGreaterThan(0);

    $boots = Equipment::query()->create([
        'name' => 'Ботинки ноль',
        'value' => null,
        'measurement_unit_id' => $sizeUnitId,
        'is_catalog' => true,
    ]);

    $response = $this->actingAs($ctx['foreman'])->from(route('applications.create'))->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $boots->id,
                'quantity' => '0',
                'measurement_type' => 'clothing_size',
                'quantity_unit' => 'разм',
                'size_value' => 'M',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.quantity');
    expect(Application::query()->count())->toBe(0);
});

test('reserve equipment warehouse name uses full name from application raw_input', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Спец склад имя');

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_name' => 'спец одежда',
                'quantity' => 8,
                'measurement_type' => 'clothing_size',
                'quantity_unit' => 'разм',
                'size_value' => 'M',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->firstOrFail();
    $item = ApplicationItem::query()->where('application_id', $app->id)->sole();
    expect($item->raw_input)->toBe('спец одежда');

    $equipment = Equipment::query()->create([
        'name' => 'спец [РЕЗЕРВ заявка '.$app->id.']',
        'value' => 'M',
        'measurement_unit_id' => (int) MeasurementUnit::query()
            ->where('code', 'разм')
            ->whereHas('unitType', fn ($q) => $q->where('code', 'clothing_size'))
            ->value('id'),
        'is_catalog' => false,
    ]);

    $item->update(['equipment_id' => $equipment->id, 'equipment_name' => null, 'raw_input' => 'спец одежда']);

    expect(\App\Support\ReserveEquipmentDisplayName::resolve($equipment->name, $equipment->id))
        ->toBe('спец одежда [РЕЗЕРВ заявка '.$app->id.']');
});

test('stored size for reserve clothing line falls back to equipment value', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Размер из eq');
    $sizeUnitId = (int) MeasurementUnit::query()
        ->where('code', 'разм')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'clothing_size'))
        ->value('id');

    $equipment = Equipment::query()->create([
        'name' => 'спец одежда [РЕЗЕРВ заявка 101]',
        'value' => 'XXL',
        'measurement_unit_id' => $sizeUnitId,
        'is_catalog' => false,
    ]);

    $app = Application::query()->create([
        'subdivision_id' => $ctx['subdivision']->id,
        'user_id' => $ctx['foreman']->id,
        'desired_delivery_date' => now()->addDays(3),
        'application_status_id' => 2,
    ]);

    $item = ApplicationItem::query()->create([
        'application_id' => $app->id,
        'equipment_id' => $equipment->id,
        'quantity' => 8,
        'is_checked' => true,
        'measurement_type' => 'clothing_size',
        'quantity_unit' => 'разм',
    ]);

    expect($item->fresh()->storedSizeValue())->toBe('XXL');
});

test('truncated catalog name does not block reserve clothing delivery line', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Спец префикс');
    $sizeUnitId = (int) MeasurementUnit::query()
        ->where('code', 'разм')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'clothing_size'))
        ->value('id');

    $catalogEquipment = Equipment::query()->create([
        'name' => 'спец',
        'value' => null,
        'measurement_unit_id' => $sizeUnitId,
        'is_catalog' => true,
    ]);

    $reserveEquipment = Equipment::query()->create([
        'name' => 'спец одежда [РЕЗЕРВ заявка 102]',
        'value' => 'M',
        'measurement_unit_id' => $sizeUnitId,
        'is_catalog' => false,
    ]);

    $app = Application::query()->create([
        'subdivision_id' => $ctx['subdivision']->id,
        'user_id' => $ctx['foreman']->id,
        'desired_delivery_date' => now()->addDays(7),
        'application_status_id' => 2,
        'approved_by_user_id' => $ctx['foreman']->id,
        'management_supply_items_saved_at' => now(),
    ]);

    $catalogItem = ApplicationItem::query()->create([
        'application_id' => $app->id,
        'equipment_id' => $catalogEquipment->id,
        'quantity' => 1,
        'is_checked' => true,
        'measurement_type' => 'clothing_size',
        'quantity_unit' => 'разм',
        'size_value' => 'M',
    ]);

    $reserveItem = ApplicationItem::query()->create([
        'application_id' => $app->id,
        'equipment_id' => $reserveEquipment->id,
        'quantity' => 5,
        'is_checked' => true,
        'measurement_type' => 'clothing_size',
        'quantity_unit' => 'разм',
    ]);

    $reserveItem->setRelation('application', $app->load('items'));
    expect($reserveItem->isMisplacedCatalogReserveDuplicateLine($app->items))->toBeFalse();
    expect($catalogItem->isMisplacedCatalogReserveDuplicateLine($app->items))->toBeFalse();
});

test('application reserve line on main warehouse can be marked delivery in transit', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Спец в пути');
    $mainWarehouse = \App\Support\AdministrationWarehouse::resolvePrimaryWarehouse();
    expect($mainWarehouse)->not->toBeNull();

    $sizeUnitId = (int) MeasurementUnit::query()
        ->where('code', 'разм')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'clothing_size'))
        ->value('id');

    $equipment = Equipment::query()->create([
        'name' => 'спец одежда [РЕЗЕРВ заявка 99]',
        'value' => 'M',
        'measurement_unit_id' => $sizeUnitId,
        'is_catalog' => false,
    ]);

    $app = Application::query()->create([
        'subdivision_id' => $ctx['subdivision']->id,
        'user_id' => $ctx['foreman']->id,
        'desired_delivery_date' => now()->addDays(7),
        'application_status_id' => 2,
        'approved_by_user_id' => $ctx['foreman']->id,
        'management_supply_items_saved_at' => now(),
    ]);

    $item = ApplicationItem::query()->create([
        'application_id' => $app->id,
        'equipment_id' => $equipment->id,
        'quantity' => 5,
        'is_checked' => true,
        'size_value' => 'M',
        'measurement_type' => 'clothing_size',
        'quantity_unit' => 'разм',
        'raw_input' => 'спец одежда',
    ]);

    $receiptTypeId = \App\Models\MaterialStockMovementType::idFor(\App\Models\MaterialStockMovementType::NAME_RECEIPT);
    \App\Models\MaterialStockMovement::query()->create([
        'equipment_id' => $equipment->id,
        'warehouse_id' => (int) $mainWarehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 5,
    ]);

    $item->setRelation('application', $app);
    $physical = \App\Support\ApplicationCatalogStockAvailability::physicalBalanceForApplicationItem(
        $item,
        (int) $mainWarehouse->id
    );

    expect($item->isApplicationReserveEquipmentLine())->toBeTrue();
    expect($item->canMarkDeliveryInTransit())->toBeTrue();
    expect($item->canMarkCatalogDeliveryInTransit($physical, collect([$item])))->toBeTrue();
});

test('overflow line display name uses full custom clothing name from sibling', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Спец overflow label');

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_name' => 'спец одежда',
                'quantity' => 8,
                'measurement_type' => 'clothing_size',
                'quantity_unit' => 'разм',
                'size_value' => 'M',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->firstOrFail();
    $mainItem = ApplicationItem::query()->where('application_id', $app->id)->sole();

    $overflow = ApplicationItem::query()->create([
        'application_id' => $app->id,
        'equipment_id' => null,
        'equipment_name' => 'спец (+на согласовании: 8 шт., размер M)',
        'size_value' => 'M',
        'quantity' => 8,
        'measurement_type' => 'clothing_size',
        'quantity_unit' => 'разм',
        'is_checked' => true,
        'custom_equipment_supply_status_id' => ApplicationItem::CUSTOM_SUPPLY_ACCEPTED_ID,
    ]);

    expect($overflow->fresh()->equipment_display_name)->toBe('спец одежда — к заказу 8 шт., размер M');
    expect($mainItem->humanEquipmentBaseName())->toBe('спец одежда');
});

test('custom clothing on warehouse dismisses stale overflow from order form', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Спец склад overflow');
    expect(\App\Support\AdministrationWarehouse::resolvePrimaryWarehouse())->not->toBeNull();

    $supplyHead = User::query()->create([
        'surname' => 'Снаб',
        'name' => 'Одежда',
        'patronymic' => 'Тест',
        'email' => 'supply-clothing-overflow-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_name' => 'спец одежда',
                'quantity' => 8,
                'measurement_type' => 'clothing_size',
                'quantity_unit' => 'разм',
                'size_value' => 'M',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->firstOrFail();
    $mainItem = ApplicationItem::query()->where('application_id', $app->id)->sole();

    $this->actingAs($supplyHead)->post(route('applications.approval', $app), [
        'items' => [
            (string) $mainItem->id => ['is_checked' => '1'],
        ],
    ])->assertRedirect(route('applications.show', $app));

    $overflow = ApplicationItem::query()->create([
        'application_id' => $app->id,
        'equipment_id' => null,
        'equipment_name' => 'спец (+на согласовании: 8 шт., размер M)',
        'size_value' => 'M',
        'quantity' => 8,
        'measurement_type' => 'clothing_size',
        'quantity_unit' => 'разм',
        'is_checked' => true,
        'custom_equipment_supply_status_id' => ApplicationItem::CUSTOM_SUPPLY_ACCEPTED_ID,
    ]);

    $mainItem->update(['custom_equipment_supply_status_id' => ApplicationItem::CUSTOM_SUPPLY_ORDERED_ID]);
    $mainItem->update(['custom_equipment_supply_status_id' => ApplicationItem::CUSTOM_SUPPLY_IN_TRANSIT_ID]);

    $this->actingAs($supplyHead)->post(route('applications.custom-equipment-order.on-warehouse', $app), [
        'item_ids' => [(string) $mainItem->id],
    ])->assertRedirect();

    $overflow->refresh();
    expect($overflow->is_checked)->toBeFalse();
    expect($overflow->removed_at)->not->toBeNull();

    $this->actingAs($supplyHead)->get(route('applications.custom-equipment-order', $app))
        ->assertOk()
        ->assertDontSee('+на согласовании', false);
});
