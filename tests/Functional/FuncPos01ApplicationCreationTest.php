<?php

use App\Models\Application;
use Tests\Support\FunctionalScenarioFixture;

test('site foreman can create application with valid data', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-100');

    $response = $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => '2026-05-15',
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ]);

    $response->assertRedirect(route('applications.index'));
    $response->assertSessionHas('status', 'Заявка успешно создана.');
    expect(Application::query()->count())->toBe(1);
});
