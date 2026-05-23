<?php

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\Equipment;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\MeasurementUnit;
use App\Models\User;
use App\Models\Subdivision;
use App\Models\Warehouse;
use App\Support\AdministrationWarehouse;
use App\Support\ApplicationCatalogStockAvailability;
use App\Support\CommercialOfferOrderStockSplit;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

function ensureAdministrationWarehouseForStockTest(): Warehouse
{
    $subdivision = Subdivision::query()->firstOrCreate(['name' => AdministrationWarehouse::SUBDIVISION_NAME]);
    Warehouse::query()->update(['is_primary' => false]);

    return Warehouse::query()->updateOrCreate(
        [
            'subdivision_id' => $subdivision->id,
            'name' => AdministrationWarehouse::WAREHOUSE_NAME,
        ],
        ['is_primary' => true]
    );
}

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

test('commercial offer order reserves catalog piece stock on administration warehouse', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КП-резерв');
    $mainWarehouse = ensureAdministrationWarehouseForStockTest();

    $pieceUnitId = (int) MeasurementUnit::query()
        ->where('code', 'шт')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'piece'))
        ->value('id');

    $nails = Equipment::query()->create([
        'name' => 'Гвозди',
        'value' => null,
        'measurement_unit_id' => $pieceUnitId,
        'is_catalog' => true,
    ]);

    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
    MaterialStockMovement::query()->create([
        'equipment_id' => $nails->id,
        'warehouse_id' => (int) $mainWarehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 100,
    ]);

    $chief = User::query()->create([
        'surname' => 'НачКот',
        'name' => 'КП',
        'patronymic' => 'Тест',
        'email' => 'chief-co-reserve-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    \Illuminate\Support\Facades\Storage::fake('public');

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => '2026-07-01',
        'items' => [
            [
                'equipment_id' => '',
                'equipment_name' => '',
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
        'commercial_offer' => \Illuminate\Http\UploadedFile::fake()->create('kp-nails.pdf', 100, 'application/pdf'),
    ]);

    $app = Application::query()->orderByDesc('id')->first();
    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app))
        ->assertRedirect(route('applications.show', $app));

    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'commercial_offer_chief_is_checked' => '1',
    ])->assertRedirect(route('applications.show', $app));

    $director = User::query()->create([
        'surname' => 'Дир',
        'name' => 'КП',
        'patronymic' => 'Резерв',
        'email' => 'director-co-reserve-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    $this->actingAs($director)->post(route('applications.approval', $app), [
        'commercial_offer_management_is_checked' => '1',
    ])->assertRedirect(route('applications.show', $app));

    $coRow = [
        'equipment_name' => 'Гвозди',
        'measurement_type' => 'piece',
        'quantity' => 55,
        'quantity_unit' => 'шт',
    ];
    expect(CommercialOfferOrderStockSplit::resolveCatalogEquipmentIdForRow($coRow, 'Гвозди'))
        ->toBe((int) $nails->id);
    expect(ApplicationCatalogStockAvailability::physicalBalanceOnWarehouse((int) $nails->id, (int) $mainWarehouse->id))
        ->toEqual(100.0);
    $expanded = CommercialOfferOrderStockSplit::expandRows([$coRow], (int) $app->id);
    expect($expanded)->toHaveCount(1);
    expect((int) ($expanded[0]['equipment_id'] ?? 0))->toBe((int) $nails->id);

    $this->actingAs($director)->post(route('applications.commercial-offer-order-lines.store', $app), [
        'items' => [
            [
                'equipment_name' => 'Гвозди',
                'measurement_type' => 'piece',
                'quantity' => 55,
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertRedirect(route('applications.show', $app))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    $items = ApplicationItem::query()->where('application_id', $app->id)->orderBy('id')->get();
    expect($items)->toHaveCount(1);

    $reserved = $items->firstWhere(fn (ApplicationItem $i) => (int) $i->equipment_id === (int) $nails->id);
    expect($reserved)->not->toBeNull();
    expect((int) $reserved->equipment_id)->toBe((int) $nails->id);
    expect((int) $reserved->quantity)->toBe(55);
    expect($reserved->is_checked)->toBeTrue();
    expect($reserved->isCommercialOfferWarehouseReserved())->toBeTrue();
    expect($reserved->custom_equipment_supply_status_id)->toBeNull();

    expect(ApplicationCatalogStockAvailability::reservedQuantitiesByEquipmentId()[(int) $nails->id] ?? 0.0)
        ->toEqual(55.0);
});

test('commercial offer order splits catalog stock and overflow to supplier order', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КП-сплит');
    $mainWarehouse = ensureAdministrationWarehouseForStockTest();

    $pieceUnitId = (int) MeasurementUnit::query()
        ->where('code', 'шт')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'piece'))
        ->value('id');

    $nails = Equipment::query()->create([
        'name' => 'Гвозди сплит',
        'value' => null,
        'measurement_unit_id' => $pieceUnitId,
        'is_catalog' => true,
    ]);

    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
    MaterialStockMovement::query()->create([
        'equipment_id' => $nails->id,
        'warehouse_id' => (int) $mainWarehouse->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 40,
    ]);

    $chief = User::query()->create([
        'surname' => 'Нач',
        'name' => 'Сплит',
        'patronymic' => 'КП',
        'email' => 'chief-co-split-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    \Illuminate\Support\Facades\Storage::fake('public');

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => '2026-07-02',
        'items' => [
            [
                'equipment_id' => '',
                'equipment_name' => '',
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
        'commercial_offer' => \Illuminate\Http\UploadedFile::fake()->create('kp-split.pdf', 100, 'application/pdf'),
    ]);

    $app = Application::query()->orderByDesc('id')->first();
    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app));
    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'commercial_offer_chief_is_checked' => '1',
    ]);

    $director = User::query()->create([
        'surname' => 'Дир',
        'name' => 'Сплит',
        'patronymic' => 'КП',
        'email' => 'director-co-split-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    $this->actingAs($director)->post(route('applications.approval', $app), [
        'commercial_offer_management_is_checked' => '1',
    ]);

    $this->actingAs($director)->post(route('applications.commercial-offer-order-lines.store', $app), [
        'items' => [
            [
                'equipment_name' => 'Гвозди сплит',
                'measurement_type' => 'piece',
                'quantity' => 55,
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertSessionHasNoErrors();

    $items = ApplicationItem::query()->where('application_id', $app->id)->orderBy('id')->get();
    expect($items)->toHaveCount(2);

    $fromStock = $items->firstWhere('equipment_id', $nails->id);
    expect($fromStock)->not->toBeNull();
    expect((int) $fromStock->quantity)->toBe(40);
    expect($fromStock->isCommercialOfferWarehouseReserved())->toBeTrue();

    $toOrder = $items->firstWhere('equipment_id', null);
    expect($toOrder)->not->toBeNull();
    expect((int) $toOrder->quantity)->toBe(15);
    expect($toOrder->isOrderedFromCommercialOffer())->toBeTrue();
});
