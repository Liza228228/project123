<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Для выполненных заявок Козлова с актом установки и фото: поступление оборудования
 * на склад подразделения-получателя (как «Доставлено») и полное списание по акту.
 */
class KozlovCompletedApplicationsWarehouseSeeder extends Seeder
{
    private const KOZLOV_EMAIL = 'Kozlov@mail.ru';

    private const ISSUE_COMMENT = 'Списание по акту установки (оборудование смонтировано).';

    public function run(): void
    {
        $kozlov = User::query()->where('email', self::KOZLOV_EMAIL)->first();
        $actor = User::query()->where('role_id', 1)->orderBy('id')->first();
        $completedId = ApplicationStatus::idFor(ApplicationStatus::NAME_COMPLETED);

        if (! $kozlov || ! $actor || $completedId <= 0) {
            return;
        }

        $applications = Application::query()
            ->where('user_id', (int) $kozlov->id)
            ->where('responsible_user_id', (int) $kozlov->id)
            ->where('application_status_id', $completedId)
            ->whereNotNull('act_of_installation')
            ->where('act_of_installation', '!=', '')
            ->whereHas('installationActPhotos')
            ->with(['items', 'subdivision', 'installationActPhotos'])
            ->orderBy('id')
            ->get();

        foreach ($applications as $application) {
            DB::transaction(function () use ($application, $actor): void {
                $this->receiptAndWriteOffForApplication($application, $actor);
            });

            $application->refresh();
            $application->load(['items', 'installationActPhotos']);
            $application->archiveIfEligible();
        }
    }

    private function receiptAndWriteOffForApplication(Application $application, User $actor): void
    {
        $warehouseId = $this->resolveRecipientWarehouseId((int) $application->subdivision_id);
        if ($warehouseId <= 0) {
            return;
        }

        $application->loadMissing(['items', 'subdivision']);
        $actorId = (int) $actor->id;

        foreach ($application->items as $item) {
            if (! $this->isDeliveredCatalogItem($item)) {
                continue;
            }

            if ((int) ($item->delivery_warehouse_id ?? 0) !== $warehouseId) {
                $item->update([
                    'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
                    'delivery_warehouse_id' => $warehouseId,
                ]);
                $item->refresh();
            }

            $this->ensureDeliveryReceipt($application, $item, $warehouseId, $actorId);
            $this->ensureInstallationWriteOff($application, $item, $warehouseId, $actorId);
        }
    }

    private function isDeliveredCatalogItem(ApplicationItem $item): bool
    {
        if (! $item->is_checked || $item->equipment_id === null) {
            return false;
        }

        return $item->resolvedDeliveryStatus() === ApplicationItem::DELIVERY_DELIVERED;
    }

    private function resolveRecipientWarehouseId(int $subdivisionId): int
    {
        if ($subdivisionId <= 0) {
            return 0;
        }

        return (int) (Warehouse::query()
            ->where('subdivision_id', $subdivisionId)
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function ensureDeliveryReceipt(
        Application $application,
        ApplicationItem $item,
        int $warehouseId,
        int $actorId,
    ): void {
        $docRef = $this->deliveryReceiptDocumentRef((int) $application->id, (int) $item->id, $warehouseId);
        $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);

        $alreadyReceived = MaterialStockMovement::query()
            ->where('material_stock_movement_type_id', $receiptTypeId)
            ->whereCorrelationKey($docRef)
            ->exists();

        if ($alreadyReceived) {
            return;
        }

        MaterialStockMovement::query()->create([
            'equipment_id' => (int) $item->equipment_id,
            'warehouse_id' => $warehouseId,
            'material_stock_movement_type_id' => $receiptTypeId,
            'quantity' => (float) $item->quantity,
            'unit_price' => null,
            'counterparty' => 'Доставка по заявке №'.$application->id,
            'comment' => MaterialStockMovement::packCommentWithCorrelation(
                $docRef,
                'Поступление на склад получателя по отметке «Доставлено» (сидер).'
            ),
            'created_by_user_id' => $actorId,
        ]);
    }

    private function ensureInstallationWriteOff(
        Application $application,
        ApplicationItem $item,
        int $warehouseId,
        int $actorId,
    ): void {
        $remaining = $this->remainingInstallationIssueQuantity($application, $item);
        if ($remaining < 0.0005) {
            return;
        }

        $equipmentId = (int) $item->equipment_id;
        if ($this->warehouseEquipmentBalance($equipmentId, $warehouseId) < $remaining - 0.0005) {
            $this->ensureDeliveryReceipt($application, $item, $warehouseId, $actorId);
        }

        if ($this->warehouseEquipmentBalance($equipmentId, $warehouseId) < $remaining - 0.0005) {
            return;
        }

        $docRef = $this->installationIssueDocumentRef((int) $application->id, (int) $item->id);

        MaterialStockMovement::query()->create([
            'equipment_id' => $equipmentId,
            'warehouse_id' => $warehouseId,
            'material_stock_movement_type_id' => MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE),
            'quantity' => $remaining,
            'unit_price' => null,
            'counterparty' => 'Заявка №'.$application->id.' / '.$application->subdivision?->name,
            'comment' => MaterialStockMovement::packCommentWithCorrelation($docRef, self::ISSUE_COMMENT),
            'created_by_user_id' => $actorId,
        ]);
    }

    private function deliveryReceiptDocumentRef(int $applicationId, int $itemId, int $warehouseId): string
    {
        return 'APP:'.$applicationId.':ITEM:'.$itemId.':DELIVERY-RCPT:WH:'.$warehouseId;
    }

    private function installationIssueDocumentRef(int $applicationId, int $itemId): string
    {
        return 'APP:'.$applicationId.':ITEM:'.$itemId.':INSTALL';
    }

    private function installationIssuedQuantityForItem(Application $application, ApplicationItem $item): float
    {
        $docRef = $this->installationIssueDocumentRef((int) $application->id, (int) $item->id);

        return (float) MaterialStockMovement::query()
            ->where('material_stock_movement_type_id', MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE))
            ->whereCorrelationKey($docRef)
            ->sum('quantity');
    }

    private function remainingInstallationIssueQuantity(Application $application, ApplicationItem $item): float
    {
        return max(0.0, (float) $item->quantity - $this->installationIssuedQuantityForItem($application, $item));
    }

    private function warehouseEquipmentBalance(int $equipmentId, int $warehouseId): float
    {
        $issueId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
        $sum = MaterialStockMovement::query()
            ->where('equipment_id', $equipmentId)
            ->where('warehouse_id', $warehouseId)
            ->selectRaw('COALESCE(SUM(CASE WHEN material_stock_movement_type_id = ? THEN -quantity ELSE quantity END), 0) as balance', [$issueId])
            ->value('balance');

        return (float) $sum;
    }
}
