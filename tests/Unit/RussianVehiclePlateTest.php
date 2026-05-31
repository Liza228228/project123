<?php

// свой код проекта
use App\Support\RussianVehiclePlate;

test('normalizes latin lookalikes and validates standard plate', function (): void {
    expect(RussianVehiclePlate::normalize('a123bc77'))->toBe('А123ВС77');
    expect(RussianVehiclePlate::isValid('А123ВС77'))->toBeTrue();
    expect(RussianVehiclePlate::isValid('цугапрцурпацунпнцупнгц'))->toBeFalse();
    expect(RussianVehiclePlate::isValid('А12ВС77'))->toBeFalse();
});

test('formats plate with spaces for display', function (): void {
    expect(RussianVehiclePlate::formatWithSpaces('А123ВС77'))->toBe('А 123 ВС 77');
    expect(RussianVehiclePlate::formatWithSpaces('a123bc177'))->toBe('А 123 ВС 177');
});
