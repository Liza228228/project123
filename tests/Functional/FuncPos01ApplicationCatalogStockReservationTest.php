<?php

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\Equipment;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\MeasurementUnit;
use App\Models\User;
use App\Support\ApplicationCatalogStockAvailability;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('approved catalog quantity reserves main warehouse stock for other applications', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Труба резерв тест');
    $mainWarehouse = \App\Support\AdministrationWarehouse::resolvePrimaryWarehouse();
    expect($mainWarehouse)->not->toBeNull();

    MaterialStockMovement::query()->where('equipment_id', $ctx['equipment']->id)->delete();

    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
    MaterialStockMovement::query()->create([
        'equipment_id' => $ctx['equipment']->id,
        'warehouse_id' => (int) $mainWarehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 10,
    ]);

    $supplyHead = User::query()->create([
        'surname' => 'Снаб',
        'name' => 'Нач',
        'patronymic' => 'Тест',
        'email' => 'supply-reserve-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'submit_for_management',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => '2026-06-01',
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 8,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
                'size_value' => '',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app1 = Application::query()->first();
    $item1 = ApplicationItem::query()->where('application_id', $app1->id)->sole();

    $this->actingAs($supplyHead)->post(route('applications.approval', $app1), [
        'items' => [
            (string) $item1->id => ['is_checked' => '1'],
        ],
    ])->assertRedirect(route('applications.show', $app1));

    $app1->refresh();
    $item1->refresh();
    expect($app1->managementHasSavedApproval())->toBeTrue();
    expect($item1->is_checked)->toBeTrue();

    expect(ApplicationCatalogStockAvailability::reservedQuantitiesByEquipmentId()[(int) $ctx['equipment']->id] ?? 0.0)
        ->toEqual(8.0);

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => '2026-06-15',
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 5,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
                'size_value' => '',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app2 = Application::query()->orderByDesc('id')->first();
    $items2 = ApplicationItem::query()->where('application_id', $app2->id)->orderBy('id')->get();
    expect($items2)->toHaveCount(2);

    $catalogLine = $items2->firstWhere('equipment_id', $ctx['equipment']->id);
    expect($catalogLine)->not->toBeNull();
    expect((int) $catalogLine->quantity)->toBe(2);

    $orderLine = $items2->firstWhere('equipment_id', null);
    expect($orderLine)->not->toBeNull();
    expect((int) $orderLine->quantity)->toBe(3);
});

test('size-type catalog stock and reservation are tracked per size variant', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Труба резерв тест');
    $mainWarehouse = \App\Support\AdministrationWarehouse::resolvePrimaryWarehouse();
    expect($mainWarehouse)->not->toBeNull();

    $sizeUnitId = (int) MeasurementUnit::query()
        ->where('code', 'разм')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'clothing_size'))
        ->value('id');
    expect($sizeUnitId)->toBeGreaterThan(0);

    $gloves = Equipment::query()->create([
        'name' => 'Перчатки диэлектрические',
        'value' => null,
        'measurement_unit_id' => $sizeUnitId,
        'is_catalog' => true,
    ]);

    MaterialStockMovement::query()->where('equipment_id', $gloves->id)->delete();

    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
    foreach (['M' => 3, 'L' => 10] as $size => $qty) {
        MaterialStockMovement::query()->create([
            'equipment_id' => $gloves->id,
            'warehouse_id' => (int) $mainWarehouse->id,
            'material_stock_movement_type_id' => $receiptTypeId,
            'quantity' => $qty,
            'receipt_variant' => $size,
        ]);
    }

    $supplyHead = User::query()->create([
        'surname' => 'Снаб',
        'name' => 'Размер',
        'patronymic' => 'Тест',
        'email' => 'supply-size-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'submit_for_management',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => '2026-06-01',
        'items' => [
            [
                'equipment_id' => $gloves->id,
                'quantity' => 2,
                'measurement_type' => 'clothing_size',
                'quantity_unit' => 'разм',
                'size_value' => 'M',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app1 = Application::query()->orderByDesc('id')->first();
    $item1 = ApplicationItem::query()
        ->where('application_id', $app1->id)
        ->where('equipment_id', $gloves->id)
        ->first();
    expect($item1)->not->toBeNull();

    $this->actingAs($supplyHead)->post(route('applications.approval', $app1), [
        'items' => [
            (string) $item1->id => ['is_checked' => '1'],
        ],
    ])->assertRedirect(route('applications.show', $app1));

    $app1->refresh();
    $item1->refresh();
    $item1->load('manualDetail');
    expect($app1->approved_by_user_id)->not->toBeNull();
    expect($item1->is_checked)->toBeTrue();
    expect($item1->storedSizeValue())->toBe('M');

    expect(ApplicationCatalogStockAvailability::reservedQuantityForEquipment((int) $gloves->id, null, 'M'))
        ->toEqual(2.0);
    expect(ApplicationCatalogStockAvailability::reservedQuantityForEquipment((int) $gloves->id, null, 'L'))
        ->toEqual(0.0);

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => '2026-06-15',
        'items' => [
            [
                'equipment_id' => $gloves->id,
                'quantity' => 3,
                'measurement_type' => 'clothing_size',
                'quantity_unit' => 'разм',
                'size_value' => 'M',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app2 = Application::query()->orderByDesc('id')->first();
    $items2 = ApplicationItem::query()->where('application_id', $app2->id)->orderBy('id')->get();
    expect($items2)->toHaveCount(2);

    $catalogLine = $items2->first(fn (ApplicationItem $i) => (int) $i->equipment_id === (int) $gloves->id);
    expect($catalogLine)->not->toBeNull();
    expect((int) $catalogLine->quantity)->toBe(1);
    expect($catalogLine->size_value)->toBe('M');

    $orderLine = $items2->firstWhere('equipment_id', null);
    expect($orderLine)->not->toBeNull();
    expect((int) $orderLine->quantity)->toBe(2);
});
