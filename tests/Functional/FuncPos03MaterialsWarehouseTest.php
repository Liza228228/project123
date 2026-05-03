<?php

use App\Models\Equipment;
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
        'value' => '100',
        'measurement_type' => 'length',
        'measurement_unit_id' => $meterUnitId,
    ]);

    $catalogResponse->assertRedirect(route('materials.index'));
    $catalogResponse->assertSessionHas('status', 'Оборудование добавлено в справочник.');

    $equipmentId = (int) Equipment::query()->where('name', $uniqueName)->value('id');
    expect($equipmentId)->toBeGreaterThan(0);

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
