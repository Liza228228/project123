<?php

// вспомогательная логика
namespace App\Support;

use App\Models\MeasurementUnit;
use App\Models\Subdivision;
use App\Models\UnitType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
final class LayoutFormOptions
{
    public static function measurementMetaForUi(): array
    {
        $unitsByType = [];
        $unitToType = [];

        $rows = MeasurementUnit::query()
            ->with('unitType:id,code,name')
            ->orderBy('unit_type_id')
            ->orderBy('id')
            ->get(['unit_type_id', 'code']);

        foreach ($rows as $row) {
            $typeCode = trim((string) ($row->unitType?->code ?? ''));
            $unitCode = trim((string) $row->code);
            if ($typeCode === '' || $unitCode === '') {
                continue;
            }
            $unitsByType[$typeCode] ??= [];
            if (! in_array($unitCode, $unitsByType[$typeCode], true)) {
                $unitsByType[$typeCode][] = $unitCode;
            }
            $unitToType[$unitCode] = $typeCode;
        }

        if ($unitsByType === []) {
            $unitsByType = [
                'piece' => ['шт'],
                'mass' => ['г', 'кг', 'т'],
                'length' => ['мм', 'см', 'м', 'км'],
                'clothing_size' => ['разм'],
            ];
            foreach ($unitsByType as $typeCode => $codes) {
                foreach ($codes as $unitCode) {
                    $unitToType[$unitCode] = $typeCode;
                }
            }
        }

        $typeOptions = [];
        $types = UnitType::query()
            ->orderBy('id')
            ->get(['code', 'name']);

        foreach ($types as $type) {
            $code = trim((string) $type->code);
            if ($code === '' || ! isset($unitsByType[$code])) {
                continue;
            }
            $typeOptions[$code] = trim((string) $type->name) !== '' ? (string) $type->name : $code;
        }

        if ($typeOptions === []) {
            $typeOptions = [
                'piece' => 'Штучные',
                'mass' => 'Масса',
                'length' => 'Длина',
                'clothing_size' => 'Размер',
            ];
        }

        $defaultType = array_key_exists('piece', $unitsByType) ? 'piece' : (array_key_first($unitsByType) ?: 'piece');
        $defaultUnit = $unitsByType[$defaultType][0] ?? 'шт';

        return [
            'typeOptions' => $typeOptions,
            'unitsByType' => $unitsByType,
            'unitToType' => $unitToType,
            'defaultType' => $defaultType,
            'defaultUnit' => $defaultUnit,
        ];
    }
    public static function subdivisionsForUser(?User $user): Collection
    {
        if (! $user) {
            return new Collection;
        }

        if ($user->hasRoleId(4)) {
            $query = $user->assignedSubdivisions()->active()->orderBy('name');
            if (($adminId = AdministrationWarehouse::subdivisionId()) !== null) {
                $query->where('subdivisions.id', '!=', $adminId);
            }

            return $query->get(['subdivisions.id', 'subdivisions.name']);
        }

        if ($user->hasRoleId(User::BOILER_CHIEF_ROLE_ID)) {
            return $user->boilerChiefSubdivisions()
                ->active()
                ->orderBy('subdivisions.name')
                ->get(['subdivisions.id', 'subdivisions.name']);
        }

        return Subdivision::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);
    }
    public static function subdivisionWarehouseOptionsForUser(?User $user): array
    {
        $subdivisions = static::subdivisionsForUser($user);
        if ($subdivisions->isEmpty()) {
            return [];
        }

        $subdivisions->load(['warehouses' => fn ($q) => $q->orderBy('name')]);

        return static::buildSubdivisionWarehouseOptions($subdivisions);
    }
    private static function buildSubdivisionWarehouseOptions(Collection $subdivisions): array
    {
        $options = [];

        foreach ($subdivisions as $subdivision) {
            $subName = trim((string) $subdivision->name);
            if ($subName === '') {
                continue;
            }
            foreach ($subdivision->warehouses as $warehouse) {
                $warehouseName = trim((string) $warehouse->name);
                if ($warehouseName === '') {
                    continue;
                }
                $address = trim((string) $warehouse->formatted_address);
                $displayName = 'Склад «'.$warehouseName.'»';
                $pdfLine = $subName !== '' ? $subName.', '.$displayName : $displayName;
                $options[] = [
                    'value' => 'warehouse:'.$warehouse->id,
                    'kind' => 'warehouse',
                    'label' => $displayName.' ('.$subName.')',
                    'display_name' => $displayName,
                    'pdf_line' => $pdfLine,
                    'address' => $address,
                    'subdivision_name' => $subName,
                ];
            }
        }

        return $options;
    }

    public static function defaultWarehouseRefForSubdivision(?User $user, int $subdivisionId): ?string
    {
        if ($subdivisionId <= 0) {
            return null;
        }

        $allowedRefs = collect(self::subdivisionWarehouseOptionsForUser($user))
            ->pluck('value')
            ->filter(fn ($value) => is_string($value) && str_starts_with($value, 'warehouse:'))
            ->all();

        if ($allowedRefs === []) {
            return null;
        }

        $warehouses = Warehouse::query()
            ->where('subdivision_id', $subdivisionId)
            ->orderBy('name')
            ->get(['id']);

        foreach ($warehouses as $warehouse) {
            $ref = 'warehouse:'.$warehouse->id;
            if (in_array($ref, $allowedRefs, true)) {
                return $ref;
            }
        }

        return null;
    }
}
