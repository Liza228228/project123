<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class MeasurementQuantityUnits
{
    /**
     * @return list<string>
     */
    public static function inputUnitsForMeasurementType(string $measurementType): array
    {
        return match (trim($measurementType)) {
            'length' => ['мм', 'см', 'м'],
            'mass' => ['г', 'кг'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function inputUnitsForItem(string $measurementType, string $baseUnit): array
    {
        $options = self::inputUnitsForMeasurementType($measurementType);
        if ($options === []) {
            return [];
        }

        $canonicalBase = self::canonicalUnit($baseUnit, $measurementType);
        if ($canonicalBase !== null && ! in_array($canonicalBase, $options, true)) {
            $options[] = $canonicalBase;
        }

        return array_values(array_unique($options));
    }

    public static function supportsAlternateInputUnits(string $measurementType): bool
    {
        return self::inputUnitsForMeasurementType($measurementType) !== [];
    }

    /**
     * Множитель: количество в единице $fromUnit × factor = количество в $baseUnit позиции.
     */
    public static function factorToBaseUnit(string $fromUnit, string $baseUnit, string $measurementType): ?float
    {
        $fromSi = self::toSiQuantity(1.0, $fromUnit, $measurementType);
        $baseSi = self::toSiQuantity(1.0, $baseUnit, $measurementType);
        if ($fromSi === null || $baseSi === null || abs($baseSi) < 1e-12) {
            return null;
        }

        return $fromSi / $baseSi;
    }

    /**
     * @return array<string, float>
     */
    public static function factorsToBaseUnitForOptions(string $measurementType, string $baseUnit): array
    {
        $map = [];
        foreach (self::inputUnitsForItem($measurementType, $baseUnit) as $unit) {
            $factor = self::factorToBaseUnit($unit, $baseUnit, $measurementType);
            if ($factor !== null) {
                $map[$unit] = $factor;
            }
        }

        return $map;
    }

    public static function toBaseQuantity(float $quantity, string $fromUnit, string $baseUnit, string $measurementType): ?float
    {
        $factor = self::factorToBaseUnit($fromUnit, $baseUnit, $measurementType);

        return $factor === null ? null : $quantity * $factor;
    }

    public static function assertValidInputUnit(string $unit, string $measurementType, string $baseUnit, string $field): string
    {
        $canonical = self::canonicalUnit($unit, $measurementType);
        if ($canonical === null) {
            throw ValidationException::withMessages([
                $field => 'Выберите допустимую единицу измерения.',
            ]);
        }

        $allowed = self::inputUnitsForItem($measurementType, $baseUnit);
        if ($allowed !== [] && ! in_array($canonical, $allowed, true)) {
            throw ValidationException::withMessages([
                $field => 'Выберите допустимую единицу измерения.',
            ]);
        }

        return $canonical;
    }

    public static function canonicalUnit(string $unit, string $measurementType): ?string
    {
        $normalized = mb_strtolower(trim($unit));
        if ($normalized === '') {
            return null;
        }

        if (trim($measurementType) === 'length') {
            return match ($normalized) {
                'мм', 'mm' => 'мм',
                'см', 'cm' => 'см',
                'м', 'm', 'метр', 'метра', 'метров' => 'м',
                'км', 'km' => 'км',
                default => null,
            };
        }

        if (trim($measurementType) === 'mass') {
            return match ($normalized) {
                'г', 'гр', 'грамм', 'грамма', 'граммов', 'g' => 'г',
                'кг', 'kg', 'килограмм', 'килограмма', 'килограммов' => 'кг',
                'т', 'тн', 'тонна', 'тонны', 'тонн', 't' => 'т',
                default => null,
            };
        }

        return null;
    }

    private static function toSiQuantity(float $quantity, string $unit, string $measurementType): ?float
    {
        $canonical = self::canonicalUnit($unit, $measurementType);
        if ($canonical === null) {
            return null;
        }

        if (trim($measurementType) === 'length') {
            $metersPerUnit = match ($canonical) {
                'мм' => 0.001,
                'см' => 0.01,
                'м' => 1.0,
                'км' => 1000.0,
                default => null,
            };

            return $metersPerUnit === null ? null : $quantity * $metersPerUnit;
        }

        if (trim($measurementType) === 'mass') {
            $kgPerUnit = match ($canonical) {
                'г' => 0.001,
                'кг' => 1.0,
                'т' => 1000.0,
                default => null,
            };

            return $kgPerUnit === null ? null : $quantity * $kgPerUnit;
        }

        return null;
    }
}
