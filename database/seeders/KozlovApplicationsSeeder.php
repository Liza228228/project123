<?php

// начальные данные для базы
namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationChangeJournal;
use App\Models\ApplicationInstallationActPhoto;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\Equipment;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\Subdivision;
use App\Models\TransportOption;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class KozlovApplicationsSeeder extends Seeder
{
    private const INSTALLATION_MEDIA_PUBLIC_DIR = 'seeders/kozlov-installation';

    private const INSTALLATION_ACTS_DIR = 'installation-acts';

    private const INSTALLATION_ACT_PHOTOS_DIR = 'installation-act-photos';

    private const FOREMAN_SUBDIVISION_NAME = 'Лаборатория технического контроля';

    private const CHIEF_REJECT_REASON = 'Не подходит марка оборудования.';

    private const MGMT_REJECT_REASON = 'Нет в наличии у поставщика.';

    private ?array $installationMedia = null;

    /** @var list<int> */
    private array $installationActReadyApplicationIds = [];

    public function run(): void
    {
        $kozlov = User::query()->where('email', 'Kozlov@mail.ru')->first();
        if (! $kozlov) {
            return;
        }

        $draftId = ApplicationStatus::idForDraft();
        $pendingId = ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING);
        $approvedId = ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED);
        $partialId = ApplicationStatus::idFor(ApplicationStatus::NAME_PARTIAL);
        $rejectedId = ApplicationStatus::idFor(ApplicationStatus::NAME_REJECTED);
        $completedId = ApplicationStatus::idFor(ApplicationStatus::NAME_COMPLETED);

        $transportQuery = TransportOption::query()->orderBy('id');
        if (Schema::hasColumn('transport_options', 'plate')) {
            $transportQuery->whereNull('plate');
        }
        $transportId = (int) ($transportQuery->value('id') ?? 0);

        $subdivision = Subdivision::query()->where('name', self::FOREMAN_SUBDIVISION_NAME)->first();
        if ($subdivision === null) {
            return;
        }

        $subdivisionId = (int) $subdivision->id;
        $recipientWarehouseId = (int) (Warehouse::query()
            ->where('subdivision_id', $subdivisionId)
            ->orderBy('id')
            ->value('id') ?? 0);
        $kozlov->assignedSubdivisions()->sync([$subdivisionId]);

        $chief = User::query()
            ->where('role_id', User::BOILER_CHIEF_ROLE_ID)
            ->whereHas('boilerChiefSubdivisions', fn ($q) => $q->where('subdivisions.id', $subdivisionId))
            ->first();

        $director = User::query()->where('role_id', 1)->orderBy('id')->first();
        $supplyHead = User::query()->where('role_id', 2)->orderBy('id')->first();

        $equipmentIds = Equipment::query()
            ->where('is_catalog', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (
            $draftId <= 0
            || $pendingId <= 0
            || $approvedId <= 0
            || $partialId <= 0
            || $rejectedId <= 0
            || $completedId <= 0
            || $transportId <= 0
            || count($equipmentIds) < 6
            || $chief === null
            || $director === null
            || $recipientWarehouseId <= 0
        ) {
            return;
        }

        $this->installationActReadyApplicationIds = [];

        Application::query()
            ->where('responsible_user_id', (int) $kozlov->id)
            ->where('user_id', (int) $kozlov->id)
            ->delete();

        $context = [
            'draft_id' => $draftId,
            'pending_id' => $pendingId,
            'approved_id' => $approvedId,
            'partial_id' => $partialId,
            'rejected_id' => $rejectedId,
            'completed_id' => $completedId,
            'transport_id' => $transportId,
            'subdivision_id' => $subdivisionId,
            'kozlov_id' => (int) $kozlov->id,
            'chief_id' => (int) $chief->id,
            'director_id' => (int) $director->id,
            'supply_id' => (int) ($supplyHead?->id ?? $director->id),
            'equipment_ids' => $equipmentIds,
            'recipient_warehouse_id' => $recipientWarehouseId,
        ];

        foreach ($this->scenarios() as $index => $scenario) {
            $this->seedScenarioApplication($index, $scenario, $context, $kozlov);
        }

        $this->seedDeliveredStockForInstallationActApplications($context, $director);

        $this->attachInstallationActAndPhotoToAllCompletedApplications((int) $kozlov->id, $completedId);

        $this->call(KozlovCompletedApplicationsWarehouseSeeder::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scenarios(): array
    {
        return [
            ['label' => 'Черновик мастера', 'workflow' => 'foreman_draft', 'items' => 5, 'item_pattern' => 'draft'],
            ['label' => 'У котельной — ожидает решения', 'workflow' => 'at_boiler_chief', 'items' => 6, 'item_pattern' => 'awaiting_boiler'],
            ['label' => 'Котельная отклонила часть — мастер правит', 'workflow' => 'chief_rejected_foreman_edit', 'items' => 5, 'item_pattern' => 'chief_mixed_not_released'],
            ['label' => 'После котельной — ждёт отправки руководству', 'workflow' => 'after_chief_before_mgmt', 'items' => 4, 'item_pattern' => 'chief_all_approved'],
            ['label' => 'У руководства — на согласовании', 'workflow' => 'at_management', 'items' => 5, 'item_pattern' => 'released_awaiting_mgmt'],
            ['label' => 'У руководства — котельная отклонила часть позиций', 'workflow' => 'at_management_chief_rejected', 'items' => 6, 'item_pattern' => 'released_chief_partial'],
            ['label' => 'У руководства — частично (до сохранения)', 'workflow' => 'at_management_mixed_display', 'items' => 5, 'item_pattern' => 'released_chief_mixed_pending'],
            ['label' => 'Частично согласована', 'workflow' => 'partial_saved', 'items' => 6, 'item_pattern' => 'mgmt_partial'],
            ['label' => 'Частично согласована (вариант 2)', 'workflow' => 'partial_saved', 'items' => 5, 'item_pattern' => 'mgmt_partial_alt'],
            ['label' => 'Не согласована', 'workflow' => 'rejected_saved', 'items' => 4, 'item_pattern' => 'mgmt_rejected_all'],
            ['label' => 'Не согласована (отказ руководства)', 'workflow' => 'rejected_saved', 'items' => 5, 'item_pattern' => 'mgmt_rejected_all'],
            ['label' => 'Согласована', 'workflow' => 'approved_saved', 'items' => 5, 'item_pattern' => 'mgmt_approved_all'],
            ['label' => 'Доставлено — можно загрузить акт (1)', 'workflow' => 'ready_for_installation_act', 'items' => 5, 'item_pattern' => 'delivered_for_act', 'installation_act_ready' => true],
            ['label' => 'Доставлено — можно загрузить акт (2)', 'workflow' => 'ready_for_installation_act', 'items' => 4, 'item_pattern' => 'delivered_for_act', 'installation_act_ready' => true],
            ['label' => 'Доставлено — можно загрузить акт (3)', 'workflow' => 'ready_for_installation_act', 'items' => 6, 'item_pattern' => 'delivered_for_act', 'installation_act_ready' => true],
            ['label' => 'В пути', 'workflow' => 'in_transit', 'items' => 5, 'item_pattern' => 'all_in_transit'],
            ['label' => 'В пути — часть позиций', 'workflow' => 'in_transit', 'items' => 6, 'item_pattern' => 'mixed_transit_delivered'],
            ['label' => 'Черновик — вторая заявка', 'workflow' => 'foreman_draft', 'items' => 4, 'item_pattern' => 'draft'],
            ['label' => 'У котельной — повторное согласование', 'workflow' => 'at_boiler_chief', 'items' => 5, 'item_pattern' => 'awaiting_boiler'],
            ['label' => 'С журналом изменений — у руководства', 'workflow' => 'at_management', 'items' => 5, 'item_pattern' => 'released_awaiting_mgmt', 'change_journal' => true],
            ['label' => 'С журналом изменений — согласована', 'workflow' => 'approved_saved', 'items' => 6, 'item_pattern' => 'mgmt_approved_all', 'change_journal' => true],
            ['label' => 'После котельной — черновик 2', 'workflow' => 'after_chief_before_mgmt', 'items' => 5, 'item_pattern' => 'chief_all_approved'],
            ['label' => 'Частично согласована — 3', 'workflow' => 'partial_saved', 'items' => 5, 'item_pattern' => 'mgmt_partial'],
            ['label' => 'Не согласована — 3', 'workflow' => 'rejected_saved', 'items' => 4, 'item_pattern' => 'mgmt_rejected_all'],
            ['label' => 'Согласована — без отгрузки', 'workflow' => 'approved_saved', 'items' => 5, 'item_pattern' => 'mgmt_approved_all'],
            ['label' => 'Выполнена 1', 'workflow' => 'completed', 'items' => 5, 'item_pattern' => 'completed'],
            ['label' => 'Выполнена 2', 'workflow' => 'completed', 'items' => 6, 'item_pattern' => 'completed'],
            ['label' => 'Выполнена 3', 'workflow' => 'completed', 'items' => 5, 'item_pattern' => 'completed'],
            ['label' => 'Выполнена 4', 'workflow' => 'completed', 'items' => 4, 'item_pattern' => 'completed'],
            ['label' => 'Выполнена 5', 'workflow' => 'completed', 'items' => 5, 'item_pattern' => 'completed'],
        ];
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $ctx
     */
    private function seedScenarioApplication(int $index, array $scenario, array $ctx, User $kozlov): void
    {
        $workflow = (string) $scenario['workflow'];
        $itemsCount = (int) $scenario['items'];
        $itemPattern = (string) $scenario['item_pattern'];

        $appAttrs = $this->applicationAttributesForWorkflow($workflow, $ctx, $index);
        $appAttrs['subdivision_id'] = $ctx['subdivision_id'];
        $appAttrs['responsible_user_id'] = $ctx['kozlov_id'];
        $appAttrs['user_id'] = $ctx['kozlov_id'];
        $appAttrs['transport_option_id'] = $ctx['transport_id'];
        $appAttrs['desired_delivery_date'] = Carbon::now()->addDays(3 + $index);

        $app = Application::query()->create($appAttrs);

        $equipmentIds = $ctx['equipment_ids'];
        shuffle($equipmentIds);

        for ($line = 0; $line < $itemsCount; $line++) {
            $equipmentId = (int) $equipmentIds[$line % count($equipmentIds)];
            $itemAttrs = $this->itemAttributesForPattern($itemPattern, $line, $itemsCount, $ctx);
            $itemAttrs['application_id'] = (int) $app->id;
            $itemAttrs['equipment_id'] = $equipmentId;
            $itemAttrs['quantity'] = random_int(1, 20);
            $itemAttrs['measurement_type'] = 'piece';
            $itemAttrs['quantity_unit'] = 'шт';

            ApplicationItem::query()->create($itemAttrs);
        }

        if (! empty($scenario['change_journal'])) {
            $this->seedChangeJournal($app, $kozlov, $index);
        }

        if (! empty($scenario['installation_act_ready'])) {
            $this->installationActReadyApplicationIds[] = (int) $app->id;
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function applicationAttributesForWorkflow(string $workflow, array $ctx, int $index): array
    {
        $savedAt = Carbon::now()->subDays(max(1, 30 - $index));

        return match ($workflow) {
            'foreman_draft' => [
                'application_status_id' => $ctx['draft_id'],
                'approved_by_user_id' => null,
                'management_supply_items_saved_at' => null,
                'reason_for_refusal' => null,
            ],
            'at_boiler_chief' => [
                'application_status_id' => $ctx['pending_id'],
                'approved_by_user_id' => null,
                'management_supply_items_saved_at' => null,
                'reason_for_refusal' => null,
            ],
            'chief_rejected_foreman_edit' => [
                'application_status_id' => $ctx['draft_id'],
                'approved_by_user_id' => null,
                'management_supply_items_saved_at' => null,
                'reason_for_refusal' => null,
            ],
            'after_chief_before_mgmt' => [
                'application_status_id' => $ctx['draft_id'],
                'approved_by_user_id' => null,
                'management_supply_items_saved_at' => null,
                'reason_for_refusal' => null,
            ],
            'at_management', 'at_management_chief_rejected', 'at_management_mixed_display' => [
                'application_status_id' => $ctx['pending_id'],
                'approved_by_user_id' => $ctx['chief_id'],
                'management_supply_items_saved_at' => null,
                'reason_for_refusal' => null,
            ],
            'partial_saved' => [
                'application_status_id' => $ctx['partial_id'],
                'approved_by_user_id' => $ctx['director_id'],
                'management_supply_items_saved_at' => $savedAt,
                'reason_for_refusal' => null,
            ],
            'rejected_saved' => [
                'application_status_id' => $ctx['rejected_id'],
                'approved_by_user_id' => $ctx['director_id'],
                'management_supply_items_saved_at' => null,
                'reason_for_refusal' => 'Отказ по всем позициям (сидер).',
            ],
            'approved_saved', 'in_transit', 'ready_for_installation_act' => [
                'application_status_id' => $ctx['approved_id'],
                'approved_by_user_id' => $ctx['director_id'],
                'management_supply_items_saved_at' => $savedAt,
                'reason_for_refusal' => null,
            ],
            'completed' => [
                'application_status_id' => $ctx['completed_id'],
                'approved_by_user_id' => $ctx['director_id'],
                'management_supply_items_saved_at' => $savedAt,
                'reason_for_refusal' => null,
            ],
            default => [
                'application_status_id' => $ctx['pending_id'],
                'approved_by_user_id' => null,
                'management_supply_items_saved_at' => null,
                'reason_for_refusal' => null,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function itemAttributesForPattern(string $pattern, int $line, int $total, array $ctx): array
    {
        $half = (int) floor($total / 2);

        return match ($pattern) {
            'draft', 'awaiting_boiler' => [
                'is_checked' => false,
                'reason_not_selected' => null,
                'delivery_status_id' => null,
            ],
            'chief_mixed_not_released' => [
                'is_checked' => $line < $half,
                'reason_not_selected' => $line >= $half ? self::CHIEF_REJECT_REASON : null,
                'delivery_status_id' => null,
            ],
            'chief_all_approved' => [
                'is_checked' => true,
                'reason_not_selected' => null,
                'delivery_status_id' => null,
            ],
            'released_awaiting_mgmt' => [
                'is_checked' => true,
                'reason_not_selected' => null,
                'delivery_status_id' => null,
            ],
            'released_chief_partial' => [
                'is_checked' => $line < $half,
                'reason_not_selected' => $line >= $half ? self::CHIEF_REJECT_REASON : null,
                'delivery_status_id' => null,
            ],
            'released_chief_mixed_pending' => [
                'is_checked' => $line < max(1, $half),
                'reason_not_selected' => $line >= max(1, $half) ? self::CHIEF_REJECT_REASON : null,
                'delivery_status_id' => null,
            ],
            'mgmt_partial' => [
                'is_checked' => $line < $half,
                'reason_not_selected' => $line >= $half ? self::MGMT_REJECT_REASON : null,
                'delivery_status_id' => null,
            ],
            'mgmt_partial_alt' => [
                'is_checked' => $line < max(1, $total - 2),
                'reason_not_selected' => $line >= max(1, $total - 2) ? 'Не требуется по объекту.' : null,
                'delivery_status_id' => null,
            ],
            'mgmt_rejected_all' => [
                'is_checked' => false,
                'reason_not_selected' => self::MGMT_REJECT_REASON,
                'delivery_status_id' => null,
            ],
            'mgmt_approved_all' => [
                'is_checked' => true,
                'reason_not_selected' => null,
                'delivery_status_id' => null,
            ],
            'all_in_transit' => [
                'is_checked' => true,
                'reason_not_selected' => null,
                'delivery_status_id' => ApplicationItem::DELIVERY_IN_TRANSIT_ID,
            ],
            'mixed_transit_delivered' => [
                'is_checked' => true,
                'reason_not_selected' => null,
                'delivery_status_id' => $line % 2 === 0
                    ? ApplicationItem::DELIVERY_IN_TRANSIT_ID
                    : ApplicationItem::DELIVERY_DELIVERED_ID,
            ],
            'mixed_delivered_transit' => [
                'is_checked' => true,
                'reason_not_selected' => null,
                'delivery_status_id' => $line === 0
                    ? ApplicationItem::DELIVERY_DELIVERED_ID
                    : ApplicationItem::DELIVERY_IN_TRANSIT_ID,
            ],
            'completed' => [
                'is_checked' => true,
                'reason_not_selected' => null,
                'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
            ],
            'delivered_for_act' => [
                'is_checked' => true,
                'reason_not_selected' => null,
                'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
                'delivery_warehouse_id' => (int) $ctx['recipient_warehouse_id'],
            ],
            default => [
                'is_checked' => false,
                'reason_not_selected' => null,
                'delivery_status_id' => null,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function seedDeliveredStockForInstallationActApplications(array $ctx, User $actor): void
    {
        if ($this->installationActReadyApplicationIds === []) {
            return;
        }

        $warehouseId = (int) $ctx['recipient_warehouse_id'];
        $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
        $actorId = (int) $actor->id;

        $applications = Application::query()
            ->whereIn('id', $this->installationActReadyApplicationIds)
            ->with(['items', 'subdivision'])
            ->get();

        foreach ($applications as $application) {
            foreach ($application->items as $item) {
                if (! $item->is_checked || $item->equipment_id === null) {
                    continue;
                }

                if ((int) ($item->delivery_warehouse_id ?? 0) !== $warehouseId) {
                    $item->update([
                        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
                        'delivery_warehouse_id' => $warehouseId,
                    ]);
                }

                $docRef = 'APP:'.$application->id.':ITEM:'.$item->id.':DELIVERY-RCPT:WH:'.$warehouseId;
                $alreadyReceived = MaterialStockMovement::query()
                    ->where('material_stock_movement_type_id', $receiptTypeId)
                    ->whereCorrelationKey($docRef)
                    ->exists();

                if ($alreadyReceived) {
                    continue;
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
                        'Поступление на склад получателя (без списания по акту).'
                    ),
                    'created_by_user_id' => $actorId,
                ]);
            }
        }
    }

    private function seedChangeJournal(Application $application, User $kozlov, int $index): void
    {
        $application->loadMissing('items');
        $firstItem = $application->items->sortBy('id')->first();
        $oldDate = $application->desired_delivery_date?->copy()->subDays(5)->format('d.m.Y') ?? '01.06.2026';
        $newDate = $application->desired_delivery_date?->format('d.m.Y') ?? '15.06.2026';

        ApplicationChangeJournal::query()->create([
            'application_id' => $application->id,
            'application_item_id' => null,
            'user_id' => $kozlov->id,
            'action' => ApplicationChangeJournal::ACTION_UPDATED,
            'field_key' => ApplicationChangeJournal::FIELD_DELIVERY_DATE,
            'field_label' => 'Желаемая дата поставки',
            'old_value' => $oldDate,
            'new_value' => $newDate,
            'reason' => 'Срок скорректировали после замечания котельной.',
            'created_at' => Carbon::now()->subDays(3 + ($index % 4)),
        ]);

        if ($firstItem !== null) {
            ApplicationChangeJournal::query()->create([
                'application_id' => $application->id,
                'application_item_id' => $firstItem->id,
                'user_id' => $kozlov->id,
                'action' => ApplicationChangeJournal::ACTION_UPDATED,
                'field_key' => ApplicationChangeJournal::FIELD_ITEM_UPDATED,
                'field_label' => 'Позиция оборудования',
                'old_value' => $firstItem->equipment_display_name.' × '.max(1, (int) $firstItem->quantity - 2).' шт',
                'new_value' => $firstItem->equipment_display_name.' × '.$firstItem->quantity.' шт',
                'reason' => 'Уточнили количество после отказа котельной.',
                'created_at' => Carbon::now()->subDays(2 + ($index % 3)),
            ]);
        }

        ApplicationChangeJournal::query()->create([
            'application_id' => $application->id,
            'application_item_id' => null,
            'user_id' => $kozlov->id,
            'action' => ApplicationChangeJournal::ACTION_ADDED,
            'field_key' => ApplicationChangeJournal::FIELD_ITEM_ADDED,
            'field_label' => 'Новая позиция',
            'old_value' => null,
            'new_value' => 'Насос циркуляционный × 2 шт',
            'reason' => 'Добавили позицию по итогам согласования.',
            'created_at' => Carbon::now()->subDay(),
        ]);
    }

    private function attachInstallationActAndPhotoToAllCompletedApplications(int $kozlovUserId, int $completedStatusId): void
    {
        $completedApplications = Application::query()
            ->where('user_id', $kozlovUserId)
            ->where('responsible_user_id', $kozlovUserId)
            ->where('application_status_id', $completedStatusId)
            ->orderBy('id')
            ->get();

        foreach ($completedApplications as $index => $application) {
            $this->attachInstallationActAndPhoto($application, $index);
        }
    }

    private function attachInstallationActAndPhoto(Application $application, int $completedIndex): void
    {
        $media = $this->installationMediaConfig();
        $actFilename = (string) $media['act_filename'];
        $photoFilenames = $media['photo_filenames'];
        $photoFilename = (string) $photoFilenames[$completedIndex % count($photoFilenames)];

        $applicationId = (int) $application->id;
        $actPath = 'installation-acts/'.$applicationId.'/'.$actFilename;
        $photoPath = 'installation-act-photos/'.$applicationId.'/'.$photoFilename;

        $disk = Storage::disk('public');
        $this->publishInstallationMediaFile($disk, $actPath, $actFilename, self::INSTALLATION_ACTS_DIR, $completedIndex);
        $this->publishInstallationMediaFile($disk, $photoPath, $photoFilename, self::INSTALLATION_ACT_PHOTOS_DIR, $completedIndex);

        $application->update(['act_of_installation' => $actPath]);

        $application->installationActPhotos()->delete();
        ApplicationInstallationActPhoto::query()->create([
            'application_id' => $applicationId,
            'path' => $photoPath,
        ]);
    }

    private function installationMediaConfig(): array
    {
        if ($this->installationMedia !== null) {
            return $this->installationMedia;
        }

        $configPath = database_path('seeders/data/kozlov_installation_media.php');
        $this->installationMedia = is_file($configPath)
            ? require $configPath
            : [
                'act_filename' => 'zajavka-1 (11).pdf',
                'photo_filenames' => ['frisquet.jpg'],
            ];

        return $this->installationMedia;
    }

    private function publishInstallationMediaFile(
        Filesystem $disk,
        string $targetRelativePath,
        string $filename,
        string $publicSubdir,
        int $completedIndex,
    ): void {
        $disk->makeDirectory(dirname($targetRelativePath));

        $sourceAbsolutePath = $this->resolveInstallationMediaSourcePath($publicSubdir, $filename, $completedIndex);
        if ($sourceAbsolutePath === null) {
            if ($this->command !== null) {
                $slot = $this->installationMediaSlot($completedIndex);
                $this->command->warn(
                    'Файл для сидера не найден: '.$filename
                    .' (ожидается: public/'.self::INSTALLATION_MEDIA_PUBLIC_DIR.'/'.$publicSubdir.'/'.$slot.'/).'
                );
            }

            return;
        }

        $disk->put($targetRelativePath, (string) file_get_contents($sourceAbsolutePath));
    }

    private function installationMediaSlot(int $completedIndex): int
    {
        return $completedIndex + 1;
    }

    private function resolveInstallationMediaSourcePath(string $publicSubdir, string $filename, int $completedIndex): ?string
    {
        $slot = $this->installationMediaSlot($completedIndex);
        $path = public_path(
            self::INSTALLATION_MEDIA_PUBLIC_DIR
            .DIRECTORY_SEPARATOR.$publicSubdir
            .DIRECTORY_SEPARATOR.$slot
            .DIRECTORY_SEPARATOR.$filename
        );

        return is_file($path) ? $path : null;
    }
}
