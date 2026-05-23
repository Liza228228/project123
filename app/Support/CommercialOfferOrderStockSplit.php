<?php

namespace App\Support;

use App\Models\ApplicationItem;
use App\Models\Equipment;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Schema;

/**
 * При заказе по КП: совпадение с каталогом и остатком на основном складе — резерв,
 * превышение — позиция «своё оборудование» к заказу.
 */
final class CommercialOfferOrderStockSplit
{
    /**
     * @param  list<array<string, mixed>>  $rows  строки формы «Как заказать»
     * @return list<array<string, mixed>>
     */
    public static function expandRows(array $rows, int $applicationId): array
    {
        $mainWarehouse = self::resolveMainWarehouse();
        if ($mainWarehouse === null) {
            return $rows;
        }

        $prepared = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['equipment_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $catalogId = self::resolveCatalogEquipmentIdForRow($row, $name);
            if ($catalogId !== null) {
                $prepared[] = array_merge($row, ['equipment_id' => $catalogId]);
            } else {
                $prepared[] = $row;
            }
        }

        return self::splitAgainstWarehouseStock($prepared, $mainWarehouse, $applicationId);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function resolveCatalogEquipmentIdForRow(array $row, string $freeTextName): ?int
    {
        $name = mb_strtolower(trim($freeTextName));
        if ($name === '') {
            return null;
        }

        $measurementType = trim((string) ($row['measurement_type'] ?? 'piece'));
        if ($measurementType === '') {
            $measurementType = 'piece';
        }

        $candidates = Equipment::query()
            ->with(['measurementUnit.unitType'])
            ->where('is_catalog', true)
            ->get()
            ->filter(fn (Equipment $equipment): bool => mb_strtolower(trim((string) $equipment->name)) === $name)
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        foreach ($candidates as $equipment) {
            $catalogType = trim((string) ($equipment->measurementUnit?->unitType?->code ?? 'piece'));
            if ($catalogType !== $measurementType) {
                continue;
            }
            if (PieceQuantity::isClothingMeasurement($measurementType)) {
                $rowSize = mb_strtoupper(trim((string) ($row['size_value'] ?? '')));
                $catalogSize = mb_strtoupper(trim((string) ($equipment->value ?? '')));
                if ($rowSize !== '' && $catalogSize !== '' && $rowSize !== $catalogSize) {
                    continue;
                }
            }

            return (int) $equipment->id;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function splitAgainstWarehouseStock(array $rows, Warehouse $mainWarehouse, int $applicationId): array
    {
        $catalogIds = [];
        foreach ($rows as $row) {
            $rawId = $row['equipment_id'] ?? null;
            if ($rawId !== null && $rawId !== '') {
                $catalogIds[] = (int) $rawId;
            }
        }
        $catalogIds = array_values(array_unique(array_filter($catalogIds, fn (int $id): bool => $id > 0)));
        $catalogEquipmentById = $catalogIds === []
            ? collect()
            : Equipment::query()
                ->whereIn('id', $catalogIds)
                ->where('is_catalog', true)
                ->get()
                ->keyBy('id');

        $reservedByStockKey = ApplicationCatalogStockAvailability::reservedQuantitiesByEquipmentId($applicationId);
        $virtualAvailableByStockKey = [];
        $out = [];

        foreach ($rows as $row) {
            $typeIdRaw = $row['equipment_id'] ?? null;
            $typeId = $typeIdRaw !== null && $typeIdRaw !== '' ? (int) $typeIdRaw : null;
            $name = trim((string) ($row['equipment_name'] ?? ''));

            if ($typeId === null || ! $catalogEquipmentById->has($typeId)) {
                $out[] = $row;

                continue;
            }

            $equipment = $catalogEquipmentById->get($typeId);
            $equipmentName = trim((string) $equipment->name);
            $normalized = self::normalizeRowQuantities($row);
            $requested = (int) $normalized['quantity'];
            if ($requested < 1) {
                $out[] = $row;

                continue;
            }

            $sizeVariant = PieceQuantity::isClothingMeasurement($normalized['measurement_type'])
                ? trim((string) ($normalized['size_value'] ?? ''))
                : '';
            $stockKey = ApplicationCatalogStockAvailability::stockAggregateKey(
                $typeId,
                $sizeVariant !== '' ? $sizeVariant : null
            );

            if (! isset($virtualAvailableByStockKey[$stockKey])) {
                $balance = $sizeVariant !== ''
                    ? ApplicationCatalogStockAvailability::physicalBalanceOnWarehouse(
                        $typeId,
                        (int) $mainWarehouse->id,
                        $sizeVariant
                    )
                    : self::warehouseEquipmentBalance($typeId, (int) $mainWarehouse->id);
                $reserved = (float) ($reservedByStockKey[$stockKey] ?? 0.0);
                $virtualAvailableByStockKey[$stockKey] = (int) max(0, (int) floor($balance - $reserved + 1e-9));
            }

            $fromStock = min($requested, $virtualAvailableByStockKey[$stockKey]);
            $over = $requested - $fromStock;
            $virtualAvailableByStockKey[$stockKey] -= $fromStock;

            if ($fromStock > 0) {
                $out[] = array_merge($row, [
                    'equipment_id' => $typeId,
                    'equipment_name' => '',
                    'quantity' => $fromStock,
                    'measurement_type' => $normalized['measurement_type'],
                    'quantity_unit' => $normalized['quantity_unit'],
                    'size_value' => $normalized['size_value'] ?? '',
                    '_co_from_warehouse' => true,
                ]);
            }
            if ($over > 0) {
                $out[] = [
                    'equipment_id' => null,
                    'equipment_name' => self::overflowLabel($equipmentName !== '' ? $equipmentName : 'Оборудование', $over, $normalized),
                    'quantity' => $over,
                    'measurement_type' => $normalized['measurement_type'],
                    'quantity_unit' => $normalized['quantity_unit'],
                    'size_value' => $normalized['size_value'] ?? '',
                    '_co_from_warehouse' => false,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{quantity: int, measurement_type: string, quantity_unit: string, size_value: ?string}
     */
    private static function normalizeRowQuantities(array $row): array
    {
        $measurementType = trim((string) ($row['measurement_type'] ?? 'piece')) ?: 'piece';
        $quantity = PieceQuantity::isPieceMeasurement($measurementType) || PieceQuantity::isClothingMeasurement($measurementType)
            ? PieceQuantity::normalizeStoredQuantity($row['quantity'] ?? 1, $measurementType)
            : max(1, (int) round((float) str_replace(',', '.', trim((string) ($row['quantity'] ?? 1)))));
        $quantityUnit = trim((string) ($row['quantity_unit'] ?? ''));
        $sizeValue = trim((string) ($row['size_value'] ?? ''));

        return [
            'quantity' => $quantity,
            'measurement_type' => $measurementType,
            'quantity_unit' => $quantityUnit !== '' ? mb_substr($quantityUnit, 0, ApplicationItem::QUANTITY_UNIT_MAX_LENGTH) : 'шт',
            'size_value' => $sizeValue !== '' ? mb_substr($sizeValue, 0, ApplicationItem::SIZE_VALUE_MAX_LENGTH) : null,
        ];
    }

    /**
     * @param  array{measurement_type: string, quantity_unit: string, size_value?: ?string}  $normalized
     */
    private static function overflowLabel(string $catalogName, int $overflowQty, array $normalized): string
    {
        $unit = trim((string) ($normalized['quantity_unit'] ?? 'шт'));
        $type = (string) ($normalized['measurement_type'] ?? 'piece');
        $size = trim((string) ($normalized['size_value'] ?? ''));
        if (PieceQuantity::isClothingMeasurement($type) && $size !== '') {
            $label = sprintf('%s (+на согласовании: %d шт., размер %s)', $catalogName, $overflowQty, $size);
        } else {
            $label = sprintf('%s (+на согласовании: %d %s)', $catalogName, $overflowQty, $unit);
        }

        return mb_substr($label, 0, ApplicationItem::EQUIPMENT_NAME_MAX_LENGTH);
    }

    private static function resolveMainWarehouse(): ?Warehouse
    {
        $warehouse = AdministrationWarehouse::resolvePrimaryWarehouse();
        if ($warehouse !== null) {
            return $warehouse;
        }

        if (! Schema::hasTable('warehouses')) {
            return null;
        }

        return Warehouse::query()->where('is_primary', true)->orderBy('id')->first();
    }

    private static function warehouseEquipmentBalance(int $equipmentId, int $warehouseId): float
    {
        $issueId = \App\Models\MaterialStockMovementType::idFor(\App\Models\MaterialStockMovementType::NAME_ISSUE);

        return (float) \App\Models\MaterialStockMovement::query()
            ->where('equipment_id', $equipmentId)
            ->where('warehouse_id', $warehouseId)
            ->selectRaw('COALESCE(SUM(CASE WHEN material_stock_movement_type_id = ? THEN -quantity ELSE quantity END), 0) as balance', [$issueId])
            ->value('balance');
    }
}
