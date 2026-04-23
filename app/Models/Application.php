<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Application extends Model
{
    protected $fillable = [
        'subdivision_id',
        'responsible_user_id',
        'commercial_offer_path',
        'installation_act_path',
        'desired_delivery_date',
        'approved_by_user_id',
        'user_id',
        'source_application_id',
        'transport_option_id',
        'application_status_id',
        'approval_rejection_reason',
        'boiler_chief_stage_completed_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'desired_delivery_date' => 'date',
            'boiler_chief_stage_completed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function applicationStatus(): BelongsTo
    {
        return $this->belongsTo(ApplicationStatus::class, 'application_status_id');
    }

    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ApplicationItem::class)->orderBy('id');
    }

    public function installationActPhotos(): HasMany
    {
        return $this->hasMany(ApplicationInstallationActPhoto::class)->orderBy('id');
    }

    /** Есть сохранённый файл акта и/или фото к акту. */
    public function hasInstallationActEvidence(): bool
    {
        if (filled(trim((string) ($this->installation_act_path ?? '')))) {
            return true;
        }

        if ($this->relationLoaded('installationActPhotos')) {
            return $this->installationActPhotos->isNotEmpty();
        }

        return $this->installationActPhotos()->exists();
    }

    public function sourceApplication(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_application_id');
    }

    public function transportOption(): BelongsTo
    {
        return $this->belongsTo(TransportOption::class);
    }

    public function isStatusApproved(): bool
    {
        if ($this->needsBoilerChiefReviewBeforeManagement()) {
            return false;
        }

        return $this->resolvedStatusCode() === ApplicationStatus::CODE_APPROVED;
    }

    public function isStatusRejected(): bool
    {
        if ($this->needsBoilerChiefReviewBeforeManagement()) {
            return false;
        }

        return $this->resolvedStatusCode() === ApplicationStatus::CODE_REJECTED;
    }

    public function isStatusPending(): bool
    {
        return $this->resolvedStatusCode() === ApplicationStatus::CODE_PENDING;
    }

    public function isStatusPartial(): bool
    {
        return $this->resolvedStatusCode() === ApplicationStatus::CODE_PARTIAL;
    }

    private function resolvedStatusCode(): string
    {
        $this->loadMissing('items', 'applicationStatus');

        if ($this->items->isEmpty()) {
            return ApplicationStatus::CODE_PENDING;
        }

        if ($this->needsBoilerChiefReviewBeforeManagement()) {
            return ApplicationStatus::CODE_PENDING;
        }

        $checkedCount = $this->items->where('is_checked', true)->count();
        $totalCount = $this->items->count();
        $rejectedWithReasonCount = $this->items->filter(
            fn (ApplicationItem $i) => ! (bool) $i->is_checked && trim((string) ($i->reason_not_selected ?? '')) !== ''
        )->count();
        $resolvedCount = $checkedCount + $rejectedWithReasonCount;

        if ($resolvedCount === $totalCount) {
            return ApplicationStatus::CODE_APPROVED;
        }

        if ($checkedCount === 0) {
            if (
                Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id)
                && $this->boiler_chief_stage_completed_at !== null
            ) {
                $hasMgmtReason = $this->items->contains(
                    fn (ApplicationItem $i) => trim((string) ($i->reason_not_selected ?? '')) !== ''
                );
                if (! $hasMgmtReason) {
                    return ApplicationStatus::CODE_PENDING;
                }
            }

            return ApplicationStatus::CODE_REJECTED;
        }

        return ApplicationStatus::CODE_PARTIAL;
    }

    /**
     * По подразделению назначен начальник котельной, но этап его согласования ещё не завершён.
     */
    public function needsBoilerChiefReviewBeforeManagement(): bool
    {
        if (! Schema::hasColumn('applications', 'boiler_chief_stage_completed_at')) {
            return false;
        }

        if (! Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id)) {
            return false;
        }

        return $this->boiler_chief_stage_completed_at === null;
    }

    /**
     * Этап котельной пройден, ожидается согласование позиций директором / ТД / снабжением (ещё ни одной позиции не отмечено).
     */
    public function awaitsManagementEquipmentApproval(): bool
    {
        if (! Schema::hasColumn('applications', 'boiler_chief_stage_completed_at')) {
            return false;
        }

        if (! Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id)) {
            return false;
        }

        if ($this->boiler_chief_stage_completed_at === null) {
            return false;
        }

        $this->loadMissing('items');
        if ($this->items->isEmpty()) {
            return false;
        }

        if ($this->items->where('is_checked', true)->count() > 0) {
            return false;
        }

        $hasMgmtReason = $this->items->contains(
            fn (ApplicationItem $i) => trim((string) ($i->reason_not_selected ?? '')) !== ''
        );

        return ! $hasMgmtReason;
    }

    public function itemLineIsApproved(int $itemId): bool
    {
        $this->loadMissing('items');

        return (bool) $this->items->firstWhere('id', $itemId)?->is_checked;
    }

    public function itemLineRejectionReason(int $itemId): ?string
    {
        $this->loadMissing('items');
        $r = $this->items->firstWhere('id', $itemId)?->reason_not_selected;

        $r = $r !== null ? trim((string) $r) : '';

        return $r !== '' ? $r : null;
    }

    public function itemLineBoilerChiefRejectionReason(int $itemId): ?string
    {
        $this->loadMissing('items');
        $r = $this->items->firstWhere('id', $itemId)?->reason_boiler_chief_not_selected;
        $r = $r !== null ? trim((string) $r) : '';

        return $r !== '' ? $r : null;
    }

    /**
     * Статус заявки и поле причин после сохранения согласования по позициям.
     *
     * @param  Collection<int, ApplicationItem>  $items
     * @return array{application_status_id: int, approval_rejection_reason: string|null}
     */
    public static function aggregateApprovalPayloadFromItems(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [
                'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::CODE_PENDING),
                'approval_rejection_reason' => null,
            ];
        }

        $checked = $items->where('is_checked', true)->count();
        $total = $items->count();
        $rejectedWithReasonCount = $items->filter(
            fn (ApplicationItem $i) => ! (bool) $i->is_checked && trim((string) ($i->reason_not_selected ?? '')) !== ''
        )->count();
        $resolvedCount = $checked + $rejectedWithReasonCount;
        $approvedId = ApplicationStatus::idFor(ApplicationStatus::CODE_APPROVED);
        $rejectedId = ApplicationStatus::idFor(ApplicationStatus::CODE_REJECTED);
        $partialId = ApplicationStatus::query()->where('code', ApplicationStatus::CODE_PARTIAL)->value('id');
        $partialId = $partialId !== null ? (int) $partialId : $rejectedId;

        if ($resolvedCount === $total) {
            $lines = $items
                ->filter(fn (ApplicationItem $i) => ! (bool) $i->is_checked)
                ->map(fn (ApplicationItem $i) => trim((string) ($i->reason_not_selected ?? '')))
                ->filter()
                ->unique()
                ->values();

            return [
                'application_status_id' => $approvedId,
                'approval_rejection_reason' => $lines->take(5)->implode('; ') ?: null,
            ];
        }

        if ($checked === 0) {
            $lines = $items
                ->map(fn (ApplicationItem $i) => trim((string) ($i->reason_not_selected ?? '')))
                ->filter()
                ->unique()
                ->values();
            $summary = $lines->take(5)->implode('; ');

            return [
                'application_status_id' => $rejectedId,
                'approval_rejection_reason' => $summary !== '' ? $summary : null,
            ];
        }

        return [
            'application_status_id' => $partialId,
            'approval_rejection_reason' => null,
        ];
    }

    /**
     * Та же выборка, что и в списке заявок: поиск и фильтр по статусу согласования.
     *
     * @return Builder<Application>
     */
    public static function listingQuery(Request $request): Builder
    {
        $search = trim((string) $request->input('q', ''));
        $equipmentFilter = (string) $request->input('equipment_filter', 'all');
        $allowedFilters = ['all', 'has_approved', 'has_not_approved', 'fully_approved', 'on_approval', 'needs_custom_equipment_order'];
        if (! in_array($equipmentFilter, $allowedFilters, true)) {
            $equipmentFilter = 'all';
        }

        $applications = static::query();

        static::applyArchiveFilterToListingQuery($applications, static::archiveFilterFromRequest($request));

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $applications->where(function ($query) use ($search, $like) {
                $query->whereRaw('0 = 1');
                if (ctype_digit($search)) {
                    $id = (int) $search;
                    $query->orWhere('id', $id)->orWhere('source_application_id', $id);
                }
                $query->orWhereHas('subdivision', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('responsibleUser', function ($q) use ($like) {
                        $q->where('surname', 'like', $like)
                            ->orWhere('name', 'like', $like)
                            ->orWhere('patronymic', 'like', $like);
                    })
                    ->orWhereHas('user', function ($q) use ($like) {
                        $q->where('surname', 'like', $like)
                            ->orWhere('name', 'like', $like)
                            ->orWhere('patronymic', 'like', $like);
                    })
                    ->orWhereHas('approvedBy', function ($q) use ($like) {
                        $q->where('surname', 'like', $like)
                            ->orWhere('name', 'like', $like)
                            ->orWhere('patronymic', 'like', $like);
                    })
                    ->orWhereHas('transportOption', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('items', function ($q) use ($like) {
                        $q->whereHas('manualDetail', fn ($m) => $m->where('equipment_name', 'like', $like))
                            ->orWhereHas('equipment', fn ($eq) => $eq->where('name', 'like', $like));
                    });
            });
        }

        $pendingId = ApplicationStatus::idFor(ApplicationStatus::CODE_PENDING);

        match ($equipmentFilter) {
            'has_approved' => $applications->whereHas('items', fn ($q) => $q->where('is_checked', true)),
            'has_not_approved' => $applications->whereHas('items', fn ($q) => $q->where('is_checked', false)),
            'fully_approved' => $applications
                ->whereHas('items')
                ->whereDoesntHave('items', fn ($q) => $q->where('is_checked', false)),
            'on_approval' => $applications->where('application_status_id', $pendingId),
            'needs_custom_equipment_order' => $applications->whereHas('items', function ($q) {
                $q->whereNull('equipment_id')
                    ->where('is_checked', true)
                    ->where(function ($w) {
                        $w->where('custom_equipment_supply_status_id', ApplicationItem::CUSTOM_SUPPLY_ACCEPTED_ID)
                            ->orWhereNull('custom_equipment_supply_status_id');
                    });
            }),
            default => null,
        };

        return $applications;
    }

    /** Краткое отображение позиций: «Позиция 1, Позиция 2» или одна строка */
    public function getEquipmentSummaryAttribute(): string
    {
        $names = $this->items->map(fn (ApplicationItem $item) => $item->equipment_display_name.' × '.$item->quantity_with_unit);

        return $names->isEmpty() ? '—' : $names->implode('; ');
    }

    /**
     * @return Collection<int, string>
     */
    public function approvedEquipmentLineItems(): Collection
    {
        return $this->items
            ->where('is_checked', true)
            ->sortBy('id')
            ->values()
            ->map(fn (ApplicationItem $item) => $item->equipment_display_name.' × '.$item->quantity_with_unit);
    }

    /**
     * @return Collection<int, string>
     */
    public function notApprovedEquipmentLineItems(): Collection
    {
        return $this->items
            ->where('is_checked', false)
            ->sortBy('id')
            ->values()
            ->map(fn (ApplicationItem $item) => $item->equipment_display_name.' × '.$item->quantity_with_unit);
    }

    public function getIsFullyApprovedAttribute(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }

        return $this->items->every(fn (ApplicationItem $i) => $i->is_checked);
    }

    /**
     * Согласованные позиции со своим названием, по которым ещё не отмечен заказ у поставщика («Принято по заявке»).
     */
    public function needsCustomEquipmentOrder(): bool
    {
        $this->loadMissing('items');

        return $this->items->contains(
            fn (ApplicationItem $item) => $item->canMarkCustomSupplyOrdered()
        );
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Списание с основного склада по заявке (как в {@see \App\Http\Controllers\ApplicationController::issueDocumentRef}).
     */
    public function stockIssueDocumentRefForItem(int $itemId): string
    {
        return 'APP:'.$this->id.':ITEM:'.$itemId;
    }

    /**
     * Списание со склада получателя по акту установки.
     */
    public function installationStockIssueDocumentRefForItem(int $itemId): string
    {
        return 'APP:'.$this->id.':ITEM:'.$itemId.':INSTALL';
    }

    /**
     * Сумма списаний по каталожной позиции: все расходы с привязкой к строке заявки
     * (основной склад, склад получателя по акту, любые суффиксы вроде :INSTALL — как в учёте).
     */
    public function totalIssuedQuantityForCatalogItem(ApplicationItem $item): float
    {
        if (! $item->equipment_id) {
            return 0.0;
        }

        $base = 'APP:'.$this->id.':ITEM:'.(int) $item->id;

        return (float) MaterialStockMovement::query()
            ->where('type', 'issue')
            ->where('equipment_id', (int) $item->equipment_id)
            ->where(function ($q) use ($base) {
                $q->where('document_ref', $base)
                    ->orWhere('document_ref', 'like', $base.':%');
            })
            ->sum('quantity');
    }

    /**
     * Все согласованные позиции из справочника полностью списаны со складов (по движениям учёта).
     */
    public function catalogApprovedItemsFullyIssued(): bool
    {
        $this->loadMissing('items');

        foreach ($this->items as $item) {
            if (! $item->is_checked || $item->equipment_id === null) {
                continue;
            }

            $qty = (float) $item->quantity;
            $issued = $this->totalIssuedQuantityForCatalogItem($item);
            if ($issued < $qty - 0.0005) {
                return false;
            }
        }

        return true;
    }

    /**
     * Можно прикрепить или заменить акт установки и фото: заявка полностью согласована и по каждой согласованной позиции
     * оборудование из справочника уже доставлено на склад подразделения-получателя (отметка «Доставлено»).
     */
    public function canUploadInstallationActAndPhotos(): bool
    {
        if (! $this->isStatusApproved()) {
            return false;
        }

        $this->loadMissing('items');

        foreach ($this->items as $item) {
            if (! $item->is_checked) {
                continue;
            }

            if ($item->equipment_id === null) {
                return false;
            }

            if ($item->resolvedDeliveryStatus() !== ApplicationItem::DELIVERY_DELIVERED) {
                return false;
            }

            if ((int) ($item->delivery_warehouse_id ?? 0) <= 0) {
                return false;
            }

            $targetSubdivisionId = $item->resolvedDeliveryTargetSubdivisionId();
            $item->loadMissing('deliveryWarehouse');
            $deliverySubId = $item->deliveryWarehouse?->subdivision_id !== null
                ? (int) $item->deliveryWarehouse->subdivision_id
                : null;
            if ($targetSubdivisionId !== null
                && $deliverySubId !== null
                && $deliverySubId !== $targetSubdivisionId) {
                return false;
            }
        }

        return true;
    }

    /**
     * Условия для архива: акт и фото есть, списания оборудования завершены.
     */
    public function qualifiesForCompletionArchive(): bool
    {
        if (! Schema::hasColumn('applications', 'archived_at')) {
            return false;
        }

        if ($this->archived_at !== null) {
            return false;
        }

        $this->loadMissing('items');

        if (! filled(trim((string) ($this->installation_act_path ?? '')))) {
            return false;
        }

        if ($this->relationLoaded('installationActPhotos')) {
            if ($this->installationActPhotos->isEmpty()) {
                return false;
            }
        } elseif (! $this->installationActPhotos()->exists()) {
            return false;
        }

        return $this->catalogApprovedItemsFullyIssued();
    }

    /**
     * Перенос в архив выполненных заявок (идемпотентно).
     */
    public function archiveIfEligible(): bool
    {
        if (! Schema::hasColumn('applications', 'archived_at')) {
            return false;
        }

        if ($this->archived_at !== null) {
            return false;
        }

        if (! $this->qualifiesForCompletionArchive()) {
            return false;
        }

        $completedId = ApplicationStatus::query()
            ->where('code', ApplicationStatus::CODE_COMPLETED)
            ->value('id');

        $payload = ['archived_at' => Carbon::now()];
        if ($completedId !== null) {
            $payload['application_status_id'] = (int) $completedId;
        }

        $this->forceFill($payload)->save();

        return true;
    }

    /**
     * Заявка закрыта и перенесена в архив выполненных (или помечена статусом «Выполнена»).
     */
    public function isLifecycleCompleted(): bool
    {
        if ($this->archived_at !== null) {
            return true;
        }

        $this->loadMissing('applicationStatus');

        return $this->applicationStatus?->code === ApplicationStatus::CODE_COMPLETED;
    }

    public static function archiveFilterFromRequest(Request $request): string
    {
        $value = trim((string) $request->input('archive', 'active'));

        return in_array($value, ['active', 'archived', 'all'], true) ? $value : 'active';
    }

    /**
     * @param  Builder<Application>  $applications
     */
    public static function applyArchiveFilterToListingQuery(Builder $applications, string $archiveFilter): void
    {
        if (! Schema::hasColumn('applications', 'archived_at')) {
            return;
        }

        match ($archiveFilter) {
            'active' => $applications->whereNull('archived_at'),
            'archived' => $applications->whereNotNull('archived_at'),
            default => null,
        };
    }
}
