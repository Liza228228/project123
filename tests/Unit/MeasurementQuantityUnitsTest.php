<?php

use App\Support\MeasurementQuantityUnits;

test('converts length units to item base unit meters', function (): void {
    expect(MeasurementQuantityUnits::toBaseQuantity(50000, 'мм', 'м', 'length'))->toBe(50.0);
    expect(MeasurementQuantityUnits::toBaseQuantity(150, 'см', 'м', 'length'))->toBe(1.5);
    expect(MeasurementQuantityUnits::toBaseQuantity(2, 'м', 'м', 'length'))->toBe(2.0);
});

test('converts mass units to item base unit kilograms', function (): void {
    expect(MeasurementQuantityUnits::toBaseQuantity(500, 'г', 'кг', 'mass'))->toBe(0.5);
    expect(MeasurementQuantityUnits::toBaseQuantity(3, 'кг', 'кг', 'mass'))->toBe(3.0);
});

test('alternate input units only for length and mass', function (): void {
    expect(MeasurementQuantityUnits::inputUnitsForMeasurementType('length'))->toBe(['мм', 'см', 'м']);
    expect(MeasurementQuantityUnits::inputUnitsForMeasurementType('mass'))->toBe(['г', 'кг']);
    expect(MeasurementQuantityUnits::inputUnitsForMeasurementType('piece'))->toBe([]);
});
