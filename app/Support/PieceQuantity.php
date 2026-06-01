<?php

// вспомогательная логика
namespace App\Support;

use Illuminate\Validation\ValidationException;

final class PieceQuantity
{
    public const MEASUREMENT_TYPE = 'piece';

    public const CLOTHING_MEASUREMENT_TYPE = 'clothing_size';

    public static function isPieceMeasurement(?string $measurementType): bool
    {
        return trim((string) $measurementType) === self::MEASUREMENT_TYPE;
    }

    public static function isClothingMeasurement(?string $measurementType): bool
    {
        return trim((string) $measurementType) === self::CLOTHING_MEASUREMENT_TYPE;
    }

    public static function requiresWholeQuantity(?string $measurementType, ?string $unitCode = null): bool
    {
        return self::isPieceMeasurement($measurementType)
            || self::isClothingMeasurement($measurementType)
            || self::isPieceUnitCode($unitCode);
    }

    public static function isPieceUnitCode(?string $unitCode): bool
    {
        $code = mb_strtolower(trim((string) $unitCode));

        return in_array($code, ['шт', 'штука', 'штуки'], true);
    }

    public static function isClothingUnitCode(?string $unitCode): bool
    {
        return mb_strtolower(trim((string) $unitCode)) === 'разм';
    }
    public static function quantitySuffix(?string $unitCode, ?string $measurementType = null): string
    {
        $unitCode = trim((string) $unitCode);
        if (self::isClothingMeasurement($measurementType) && $unitCode !== '' && ! self::isClothingUnitCode($unitCode)) {
            return $unitCode;
        }

        return $unitCode !== '' ? $unitCode : 'шт';
    }
    public static function formatForDisplay(float $value, ?string $unitCode = null, ?string $measurementType = null): string
    {
        if (
            self::isPieceMeasurement($measurementType)
            || self::isClothingMeasurement($measurementType)
            || self::isPieceUnitCode($unitCode)
            || self::isClothingUnitCode($unitCode)
        ) {
            return number_format((int) round($value), 0, '.', ' ');
        }

        return number_format($value, 3, '.', ' ');
    }

    public static function isWholeQuantity(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        if ($normalized === '' || ! is_numeric($normalized)) {
            return false;
        }

        $float = (float) $normalized;

        return $float > 0 && abs($float - round($float)) < 0.000001;
    }
    public static function assertWholeQuantity(mixed $value, string $field = 'quantity'): void
    {
        if (self::isWholeQuantity($value)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'Для учёта в штуках укажите целое число без дробной части.',
        ]);
    }

    public static function normalizeStoredQuantity(mixed $value, string $measurementType): int
    {
        $qty = self::isWholeQuantity($value) ? (int) round((float) str_replace(',', '.', trim((string) $value))) : 1;

        return max(1, $qty);
    }
}
