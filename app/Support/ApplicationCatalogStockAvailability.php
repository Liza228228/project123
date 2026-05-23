<?php

namespace App\Support;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\Equipment;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Доступный остаток каталожного оборудования на основном складе с учётом резерва
 * по другим заявкам после согласования снабжения (без физического списания).
 * Для типа «размер» — отдельно по каждому размеру (receipt_variant на складе).
 */
final class ApplicationCatalogStockAvailability
{
    /**
     * @return array<int|string, float> ключ: equipment_id или "equipment_id:SIZE"
     */
    public static function reservedQuantitiesByEquipmentId(?int $excludeApplicationId = null): array
    {
        $applicationIds = Application::query()
            ->notArchived()
            ->whereSupplyApprovedForCustomEquipmentWorkflow()
            ->pluck('id');

        if ($applicationIds->isEmpty()) {
            return [];
        }

        $items = ApplicationItem::query()
            ->with('manualDetail')
            ->where('application_items.is_checked', true)
            ->whereIn('application_items.application_id', $applicationIds)
            ->when($excludeApplicationId !== null, function (Builder $q) use ($excludeApplicationId): void {
                $q->where('application_items.application_id', '!=', $excludeApplicationId);
            })
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        $issuedByItemKey = self::issuedQuantitiesByApplicationItemKey();

        $byKey = [];
        foreach ($items as $item) {
            $equipmentId = self::resolveCatalogEquipmentIdForReservation($item);
            if ($equipmentId <= 0) {
                continue;
            }
            $key = self::reservationAggregateKey($equipmentId, $item);
            $itemKey = (int) $item->application_id.':'.(int) $item->id;
            $issued = $issuedByItemKey[$itemKey] ?? 0.0;
            $remaining = max(0.0, (float) $item->quantity - $issued);
            if ($remaining < 0.0005) {
                continue;
            }
            $byKey[$key] = ($byKey[$key] ?? 0.0) + $remaining;
        }

        return $byKey;
    }

    public static function reservedQuantityForEquipment(
        int $equipmentId,
        ?int $excludeApplicationId = null,
        ?string $sizeVariant = null
    ): float {
        $map = self::reservedQuantitiesByEquipmentId($excludeApplicationId);
        $key = self::stockAggregateKey($equipmentId, $sizeVariant);

        return (float) ($map[$key] ?? 0.0);
    }

    public static function availableOnMainWarehouse(
        int $equipmentId,
        float $physicalBalance,
        ?int $excludeApplicationId = null,
        ?string $sizeVariant = null
    ): float {
        $reserved = self::reservedQuantityForEquipment($equipmentId, $excludeApplicationId, $sizeVariant);

        return max(0.0, $physicalBalance - $reserved);
    }

    public static function physicalBalanceOnWarehouse(
        int $equipmentId,
        int $warehouseId,
        ?string $sizeVariant = null
    ): float {
        $issueId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
        $query = MaterialStockMovement::query()
            ->where('equipment_id', $equipmentId)
            ->where('warehouse_id', $warehouseId);

        if ($sizeVariant !== null && trim($sizeVariant) !== '') {
            $normalizedSize = mb_strtoupper(trim($sizeVariant));
            $query->whereRaw('UPPER(TRIM(COALESCE(receipt_variant, ""))) = ?', [$normalizedSize]);
        }

        $sum = $query
            ->selectRaw('COALESCE(SUM(CASE WHEN material_stock_movement_type_id = ? THEN -quantity ELSE quantity END), 0) as balance', [$issueId])
            ->value('balance');

        return (float) $sum;
    }

    /**
     * @return int|string
     */
    public static function stockAggregateKey(int $equipmentId, ?string $sizeVariant): int|string
    {
        if ($sizeVariant !== null && trim($sizeVariant) !== '') {
            return $equipmentId.':'.mb_strtoupper(trim($sizeVariant));
        }

        return $equipmentId;
    }

    /**
     * @return int|string
     */
    private static function reservationAggregateKey(int $equipmentId, ApplicationItem $item): int|string
    {
        if (PieceQuantity::isClothingMeasurement($item->storedMeasurementType())) {
            $size = $item->storedSizeValue() ?? '';
            if ($size !== '') {
                return self::stockAggregateKey($equipmentId, $size);
            }
        }

        return $equipmentId;
    }

    /**
     * @return array<string, float> "applicationId:itemId" => issued qty
     */
    private static function issuedQuantitiesByApplicationItemKey(): array
    {
        $issueId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
        $corrPrefix = preg_quote(MaterialStockMovement::CORR_PREFIX, '/');
        $pattern = '/'.$corrPrefix.'APP:(\d+):ITEM:(\d+)/';

        $map = [];
        MaterialStockMovement::query()
            ->where('material_stock_movement_type_id', $issueId)
            ->where('comment', 'like', MaterialStockMovement::CORR_PREFIX.'APP:%')
            ->get(['quantity', 'comment'])
            ->each(function (MaterialStockMovement $movement) use ($pattern, &$map): void {
                $comment = (string) $movement->comment;
                if (! preg_match($pattern, $comment, $m)) {
                    return;
                }
                $key = (int) $m[1].':'.(int) $m[2];
                $map[$key] = ($map[$key] ?? 0.0) + (float) $movement->quantity;
            });

        return $map;
    }

    /**
     * Каталожная строка или «своё» с пометкой «+на согласовании» (дробление при нехватке на складе).
     */
    private static function resolveCatalogEquipmentIdForReservation(ApplicationItem $item): int
    {
        $direct = (int) ($item->equipment_id ?? 0);
        if ($direct > 0) {
            return $direct;
        }

        $label = trim((string) ($item->equipment_name ?? ''));
        if ($label !== '' && ! str_contains($label, '+на согласовании')) {
            return 0;
        }

        $baseName = trim((string) ($item->base_name ?? ''));
        if ($baseName === '' || $baseName === '—') {
            return 0;
        }

        $catalogId = Equipment::query()
            ->where('is_catalog', true)
            ->where('name', $baseName)
            ->value('id');

        return $catalogId !== null ? (int) $catalogId : 0;
    }
}
