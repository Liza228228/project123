<?php

use App\Models\Equipment;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\MeasurementUnit;
use App\Models\User;
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
        'name' => 'Оборудование с таким названием уже есть в справочнике. Укажите другое название.',
    ]);
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
