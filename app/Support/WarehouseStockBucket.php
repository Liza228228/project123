<?php

// учёт годного и бракованного остатка на складе
namespace App\Support;

use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WarehouseStockBucket
{
    public const GOOD = 0;

    public const DEFECTIVE = 1;

    public static function balance(int $equipmentId, int $warehouseId, int $bucket = self::GOOD, ?string $receiptVariant = null): float
    {
        $issueId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
        $query = MaterialStockMovement::query()
            ->where('equipment_id', $equipmentId)
            ->where('warehouse_id', $warehouseId)
            ->where('stock_bucket', $bucket);

        if ($receiptVariant !== null && trim($receiptVariant) !== '') {
            $normalizedSize = mb_strtoupper(trim($receiptVariant));
            $query->whereRaw('UPPER(TRIM(COALESCE(receipt_variant, ""))) = ?', [$normalizedSize]);
        }

        $sum = $query
            ->selectRaw('COALESCE(SUM(CASE WHEN material_stock_movement_type_id = ? THEN -quantity ELSE quantity END), 0) as balance', [$issueId])
            ->value('balance');

        return (float) $sum;
    }

    public static function defectTransferCorrelationKey(int $applicationId, int $itemId, string $transferToken): string
    {
        return 'APP:'.$applicationId.':ITEM:'.$itemId.':DEFECT:'.$transferToken;
    }

    public static function defectDisposeCorrelationKey(int $applicationId, int $itemId, string $disposeToken): string
    {
        return 'APP:'.$applicationId.':ITEM:'.$itemId.':DISPOSE:'.$disposeToken;
    }

    public static function defectTransferCorrelationPrefix(int $applicationId, int $itemId): string
    {
        return 'APP:'.$applicationId.':ITEM:'.$itemId.':DEFECT:';
    }

    public static function defectDisposeCorrelationPrefix(int $applicationId, int $itemId): string
    {
        return 'APP:'.$applicationId.':ITEM:'.$itemId.':DISPOSE:';
    }

    public static function markedDefectiveQuantityForApplicationItem(int $applicationId, int $itemId): float
    {
        $receiptId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
        $prefix = self::defectTransferCorrelationPrefix($applicationId, $itemId);

        return (float) MaterialStockMovement::query()
            ->where('material_stock_movement_type_id', $receiptId)
            ->where('stock_bucket', self::DEFECTIVE)
            ->where(function ($q) use ($prefix): void {
                $packed = MaterialStockMovement::CORR_PREFIX.$prefix;
                $q->where('comment', 'like', $packed.'%');
            })
            ->sum('quantity');
    }

    public static function disposedDefectiveQuantityForApplicationItem(int $applicationId, int $itemId): float
    {
        $issueId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
        $prefix = self::defectDisposeCorrelationPrefix($applicationId, $itemId);

        return (float) MaterialStockMovement::query()
            ->where('material_stock_movement_type_id', $issueId)
            ->where('stock_bucket', self::DEFECTIVE)
            ->where(function ($q) use ($prefix): void {
                $packed = MaterialStockMovement::CORR_PREFIX.$prefix;
                $q->where('comment', 'like', $packed.'%');
            })
            ->sum('quantity');
    }

    public static function remainingDefectiveQuantityForApplicationItem(int $applicationId, int $itemId): float
    {
        return max(
            0.0,
            self::markedDefectiveQuantityForApplicationItem($applicationId, $itemId)
            - self::disposedDefectiveQuantityForApplicationItem($applicationId, $itemId)
        );
    }

    public static function installationIssuedQuantityForApplicationItem(int $applicationId, int $itemId): float
    {
        $docRef = 'APP:'.$applicationId.':ITEM:'.$itemId.':INSTALL';
        $issueId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);

        return (float) MaterialStockMovement::query()
            ->where('material_stock_movement_type_id', $issueId)
            ->where('stock_bucket', self::GOOD)
            ->whereCorrelationKey($docRef)
            ->sum('quantity');
    }

    public static function installationActReportQuantityForApplicationItem(
        float $orderedQuantity,
        int $applicationId,
        int $itemId,
    ): float {
        $defective = self::markedDefectiveQuantityForApplicationItem($applicationId, $itemId);
        $installable = max(0.0, $orderedQuantity - $defective);
        $issued = self::installationIssuedQuantityForApplicationItem($applicationId, $itemId);

        if ($issued > 0.0005) {
            return min($issued, $installable);
        }

        return $installable;
    }

    public static function remainingInstallationIssueQuantity(
        float $orderedQuantity,
        int $applicationId,
        int $itemId,
        int $equipmentId,
        int $warehouseId,
    ): float {
        $issued = self::installationIssuedQuantityForApplicationItem($applicationId, $itemId);
        $markedDefective = self::markedDefectiveQuantityForApplicationItem($applicationId, $itemId);
        $byLine = max(0.0, $orderedQuantity - $issued - $markedDefective);
        $goodOnWarehouse = $warehouseId > 0
            ? self::balance($equipmentId, $warehouseId, self::GOOD)
            : 0.0;

        return min($byLine, $goodOnWarehouse);
    }

    public static function maxMarkableDefectQuantity(
        float $orderedQuantity,
        int $applicationId,
        int $itemId,
        int $equipmentId,
        int $warehouseId,
    ): float {
        $issued = self::installationIssuedQuantityForApplicationItem($applicationId, $itemId);
        $markedDefective = self::markedDefectiveQuantityForApplicationItem($applicationId, $itemId);
        $byLine = max(0.0, $orderedQuantity - $issued - $markedDefective);
        $goodOnWarehouse = $warehouseId > 0
            ? self::balance($equipmentId, $warehouseId, self::GOOD)
            : 0.0;

        return min($byLine, $goodOnWarehouse);
    }

    public static function warehouseDefectTransferCorrelationKey(int $warehouseId, string $transferToken): string
    {
        return 'WH:'.$warehouseId.':DEFECT:'.$transferToken;
    }

    /**
     * SQL-выражение для колонки «Списано» в обзоре склада: расход годного без перевода в брак + утилизация брака.
     */
    public static function overviewWrittenOffQuantitySqlExpression(): string
    {
        $corr = str_replace("'", "''", MaterialStockMovement::CORR_PREFIX);
        $issue = str_replace("'", "''", MaterialStockMovementType::NAME_ISSUE);
        $good = self::GOOD;
        $defective = self::DEFECTIVE;

        return "(
            SUM(CASE
                WHEN material_stock_movements.stock_bucket = {$good}
                    AND msm_types.name = '{$issue}'
                    AND (
                        material_stock_movements.comment IS NULL
                        OR material_stock_movements.comment NOT LIKE '{$corr}%:DEFECT:%'
                    )
                THEN material_stock_movements.quantity
                ELSE 0
            END)
            + SUM(CASE
                WHEN material_stock_movements.stock_bucket = {$defective}
                    AND msm_types.name = '{$issue}'
                THEN material_stock_movements.quantity
                ELSE 0
            END)
        )";
    }

    public static function warehouseDefectDisposeCorrelationKey(int $warehouseId, string $disposeToken): string
    {
        return 'WH:'.$warehouseId.':DISPOSE:'.$disposeToken;
    }

    public static function transferGoodToDefectiveOnWarehouse(
        int $equipmentId,
        int $warehouseId,
        float $quantity,
        string $reason,
        ?int $actorUserId = null,
        ?string $receiptVariant = null,
    ): void {
        if ($quantity < 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Укажите количество больше нуля.',
            ]);
        }

        $goodBalance = self::balance($equipmentId, $warehouseId, self::GOOD, $receiptVariant);
        if ($goodBalance < $quantity - 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Недостаточно годного остатка на складе для перевода в брак.',
            ]);
        }

        $transferToken = (string) microtime(true);
        $corr = self::warehouseDefectTransferCorrelationKey($warehouseId, $transferToken);
        $issueId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
        $receiptId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
        $reason = trim($reason);
        $body = $reason !== '' ? 'Перевод в брак: '.$reason : 'Перевод в брак на основном складе.';

        DB::transaction(function () use (
            $equipmentId,
            $warehouseId,
            $quantity,
            $issueId,
            $receiptId,
            $corr,
            $body,
            $actorUserId,
            $receiptVariant,
        ): void {
            MaterialStockMovement::query()->create([
                'equipment_id' => $equipmentId,
                'warehouse_id' => $warehouseId,
                'material_stock_movement_type_id' => $issueId,
                'quantity' => $quantity,
                'stock_bucket' => self::GOOD,
                'receipt_variant' => $receiptVariant,
                'unit_price' => null,
                'counterparty' => 'Основной склад',
                'comment' => MaterialStockMovement::packCommentWithCorrelation($corr, $body),
                'created_by_user_id' => $actorUserId,
            ]);

            MaterialStockMovement::query()->create([
                'equipment_id' => $equipmentId,
                'warehouse_id' => $warehouseId,
                'material_stock_movement_type_id' => $receiptId,
                'quantity' => $quantity,
                'stock_bucket' => self::DEFECTIVE,
                'receipt_variant' => $receiptVariant,
                'unit_price' => null,
                'counterparty' => 'Основной склад',
                'comment' => MaterialStockMovement::packCommentWithCorrelation($corr, $body),
                'created_by_user_id' => $actorUserId,
            ]);
        });
    }

    public static function issueGoodFromWarehouse(
        int $equipmentId,
        int $warehouseId,
        float $quantity,
        ?string $comment = null,
        ?int $actorUserId = null,
        ?string $counterparty = null,
        ?string $receiptVariant = null,
    ): void {
        if ($quantity < 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Укажите количество больше нуля.',
            ]);
        }

        $goodBalance = self::balance($equipmentId, $warehouseId, self::GOOD, $receiptVariant);
        if ($goodBalance < $quantity - 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Недостаточно годного остатка на складе для списания.',
            ]);
        }

        $issueToken = (string) microtime(true);
        $corr = 'WH:'.$warehouseId.':ISSUE:'.$issueToken;
        $issueId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
        $body = trim((string) $comment);
        if ($body === '') {
            $body = 'Списание годного оборудования с основного склада.';
        }

        MaterialStockMovement::query()->create([
            'equipment_id' => $equipmentId,
            'warehouse_id' => $warehouseId,
            'material_stock_movement_type_id' => $issueId,
            'quantity' => $quantity,
            'stock_bucket' => self::GOOD,
            'receipt_variant' => $receiptVariant,
            'unit_price' => null,
            'counterparty' => $counterparty !== null && trim($counterparty) !== '' ? trim($counterparty) : 'Основной склад',
            'comment' => MaterialStockMovement::packCommentWithCorrelation($corr, $body),
            'created_by_user_id' => $actorUserId,
        ]);
    }

    public static function disposeDefectiveOnWarehouse(
        int $equipmentId,
        int $warehouseId,
        float $quantity,
        ?string $comment = null,
        ?int $actorUserId = null,
        ?string $receiptVariant = null,
    ): void {
        if ($quantity < 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Укажите количество больше нуля.',
            ]);
        }

        $defectiveBalance = self::balance($equipmentId, $warehouseId, self::DEFECTIVE, $receiptVariant);
        if ($defectiveBalance < $quantity - 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Недостаточно бракованного остатка на складе для утилизации.',
            ]);
        }

        $disposeToken = (string) microtime(true);
        $corr = self::warehouseDefectDisposeCorrelationKey($warehouseId, $disposeToken);
        $issueId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
        $body = trim((string) $comment);
        if ($body === '') {
            $body = 'Утилизация бракованного оборудования с основного склада.';
        }

        MaterialStockMovement::query()->create([
            'equipment_id' => $equipmentId,
            'warehouse_id' => $warehouseId,
            'material_stock_movement_type_id' => $issueId,
            'quantity' => $quantity,
            'stock_bucket' => self::DEFECTIVE,
            'receipt_variant' => $receiptVariant,
            'unit_price' => null,
            'counterparty' => 'Основной склад',
            'comment' => MaterialStockMovement::packCommentWithCorrelation($corr, $body),
            'created_by_user_id' => $actorUserId,
        ]);
    }

    public static function transferToDefective(
        int $equipmentId,
        int $warehouseId,
        float $quantity,
        int $applicationId,
        int $itemId,
        string $reason,
        ?int $actorUserId = null,
    ): void {
        if ($quantity < 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Укажите количество больше нуля.',
            ]);
        }

        $goodBalance = self::balance($equipmentId, $warehouseId, self::GOOD);
        if ($goodBalance < $quantity - 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Недостаточно годного остатка на складе для перевода в брак.',
            ]);
        }

        $transferToken = (string) microtime(true);
        $corr = self::defectTransferCorrelationKey($applicationId, $itemId, $transferToken);
        $issueId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
        $receiptId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
        $reason = trim($reason);
        $body = $reason !== '' ? 'Перевод в брак: '.$reason : 'Перевод в брак по заявке №'.$applicationId.'.';

        DB::transaction(function () use (
            $equipmentId,
            $warehouseId,
            $quantity,
            $applicationId,
            $issueId,
            $receiptId,
            $corr,
            $body,
            $actorUserId,
        ): void {
            MaterialStockMovement::query()->create([
                'equipment_id' => $equipmentId,
                'warehouse_id' => $warehouseId,
                'material_stock_movement_type_id' => $issueId,
                'quantity' => $quantity,
                'stock_bucket' => self::GOOD,
                'unit_price' => null,
                'counterparty' => 'Заявка №'.$applicationId,
                'comment' => MaterialStockMovement::packCommentWithCorrelation($corr, $body),
                'created_by_user_id' => $actorUserId,
            ]);

            MaterialStockMovement::query()->create([
                'equipment_id' => $equipmentId,
                'warehouse_id' => $warehouseId,
                'material_stock_movement_type_id' => $receiptId,
                'quantity' => $quantity,
                'stock_bucket' => self::DEFECTIVE,
                'unit_price' => null,
                'counterparty' => 'Заявка №'.$applicationId,
                'comment' => MaterialStockMovement::packCommentWithCorrelation($corr, $body),
                'created_by_user_id' => $actorUserId,
            ]);
        });
    }

    public static function disposeDefective(
        int $equipmentId,
        int $warehouseId,
        float $quantity,
        int $applicationId,
        int $itemId,
        ?string $comment = null,
        ?int $actorUserId = null,
    ): void {
        if ($quantity < 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Укажите количество больше нуля.',
            ]);
        }

        $defectiveBalance = self::balance($equipmentId, $warehouseId, self::DEFECTIVE);
        if ($defectiveBalance < $quantity - 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Недостаточно бракованного остатка на складе для утилизации.',
            ]);
        }

        $remainingForItem = self::remainingDefectiveQuantityForApplicationItem($applicationId, $itemId);
        if ($quantity > $remainingForItem + 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Нельзя утилизировать больше, чем числится браком по этой позиции заявки.',
            ]);
        }

        $disposeToken = (string) microtime(true);
        $corr = self::defectDisposeCorrelationKey($applicationId, $itemId, $disposeToken);
        $issueId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
        $body = trim((string) $comment);
        if ($body === '') {
            $body = 'Утилизация бракованного оборудования по заявке №'.$applicationId.'.';
        }

        MaterialStockMovement::query()->create([
            'equipment_id' => $equipmentId,
            'warehouse_id' => $warehouseId,
            'material_stock_movement_type_id' => $issueId,
            'quantity' => $quantity,
            'stock_bucket' => self::DEFECTIVE,
            'unit_price' => null,
            'counterparty' => 'Заявка №'.$applicationId,
            'comment' => MaterialStockMovement::packCommentWithCorrelation($corr, $body),
            'created_by_user_id' => $actorUserId,
        ]);
    }
}
