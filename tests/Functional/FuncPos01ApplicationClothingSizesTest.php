<?php

// функциональный тест
use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\Equipment;
use App\Models\MeasurementUnit;
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
