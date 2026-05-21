<?php

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use Tests\Support\FunctionalScenarioFixture;

test('catalog line splits into stock plus pending custom when request exceeds main warehouse', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Труба тестовая');

    MaterialStockMovement::query()->where('equipment_id', $ctx['equipment']->id)->delete();

    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
    MaterialStockMovement::query()->create([
        'equipment_id' => $ctx['equipment']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 10,
    ]);

    $response = $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => '2026-05-15',
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 15,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
                'size_value' => '',
            ],
        ],
    ]);

    $response->assertRedirect(route('applications.index'));
    $response->assertSessionHas('status', 'Заявка успешно создана.');

    $application = Application::query()->first();
    expect($application)->not->toBeNull();

    $items = ApplicationItem::query()->where('application_id', $application->id)->orderBy('id')->get();
    expect($items)->toHaveCount(2);

    $catalogLine = $items->firstWhere('equipment_id', $ctx['equipment']->id);
    expect($catalogLine)->not->toBeNull();
    expect((int) $catalogLine->quantity)->toBe(10);
    expect($catalogLine->custom_equipment_supply_status_id)->toBeNull();

    $orderLine = $items->firstWhere('equipment_id', null);
    expect($orderLine)->not->toBeNull();
    expect((int) $orderLine->quantity)->toBe(5);
    expect($orderLine->custom_equipment_supply_status_id)->toBe(ApplicationItem::CUSTOM_SUPPLY_PENDING_APPROVAL_ID);
    expect($orderLine->equipment_name)->toContain('+на согласовании: 5 шт');
});
