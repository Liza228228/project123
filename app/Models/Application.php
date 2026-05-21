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
        'commercial_offer',
        'act_of_installation',
        'desired_delivery_date',
        'approved_by_user_id',
        'management_supply_items_saved_at',
        'user_id',
        'source_application_id',
        'transport_option_id',
        'application_status_id',
        'reason_for_refusal',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'desired_delivery_date' => 'date',
            'archived_at' => 'datetime',
            'management_supply_items_saved_at' => 'datetime',
        ];
    }

    public function isForemanCreatedApplication(): bool
    {
        $this->loadMissing('user:id,role_id');

        return (int) ($this->user?->role_id ?? 0) === 4;
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
        if (filled(trim((string) ($this->act_of_installation ?? '')))) {
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

    /**
     * Для формы «В пути»: id способа (строка без госномера), если на заявке выбран вариант с заполненным plate.
     */
    public function transportMethodOptionIdForDeliveryForm(): ?int
    {
        if (! Schema::hasColumn('transport_options', 'plate')) {
            return $this->transport_option_id !== null ? (int) $this->transport_option_id : null;
        }

        $this->loadMissing('transportOption');
        if ($this->transport_option_id === null || ! $this->transportOption) {
            return null;
        }

        $plate = trim((string) ($this->transportOption->plate ?? ''));
        if ($plate === '') {
            return (int) $this->transport_option_id;
        }

        return TransportOption::query()
            ->whereNull('plate')
            ->where('name', $this->transportOption->name)
            ->orderBy('id')
            ->value('id');
    }

    /**
     * Строка для списка заявок: способ и госномер из справочника transport_options.
     */
    public function transportAndVehicleLine(): ?string
    {
        $this->loadMissing('transportOption');
        $opt = $this->transportOption;
        if (! $opt) {
            return null;
        }

        $name = trim((string) ($opt->name ?? ''));
        $plate = Schema::hasColumn('transport_options', 'plate')
            ? trim((string) ($opt->plate ?? ''))
            : '';

        if ($name === '' && $plate === '') {
            return null;
        }

        if ($name !== '' && $plate !== '') {
            return $name.' — '.$plate;
        }

        return $name !== '' ? $name : $plate;
    }

    /**
     * Все согласованные позиции находятся в «В пути» (поставка своего оборудования или доставка на объект).
     */
    public function isApprovedDeliveryFullyInTransit(): bool
    {
        if ($this->archived_at !== null) {
            return false;
        }

        if ($this->approved_by_user_id === null) {
            return false;
        }

        if (Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id)
            && ! $this->needsBoilerChiefReviewBeforeManagement()
            && $this->management_supply_items_saved_at === null) {
            return false;
        }

        $this->loadMissing('items');
        $checked = $this->items->where('is_checked', true);
        if ($checked->isEmpty()) {
            return false;
        }

        foreach ($checked as $item) {
            if (! $item->isInShipmentTransitState()) {
                return false;
            }
        }

        return true;
    }

    public function isStatusApproved(): bool
    {
        if ($this->needsBoilerChiefReviewBeforeManagement()) {
            return false;
        }

        return $this->resolvedStatusName() === ApplicationStatus::NAME_APPROVED;
    }

    public function isStatusRejected(): bool
    {
        if ($this->needsBoilerChiefReviewBeforeManagement()) {
            return false;
        }

        return $this->resolvedStatusName() === ApplicationStatus::NAME_REJECTED;
    }

    public function isStatusPending(): bool
    {
        return $this->resolvedStatusName() === ApplicationStatus::NAME_PENDING;
    }

    public function isStatusPartial(): bool
    {
        return $this->resolvedStatusName() === ApplicationStatus::NAME_PARTIAL;
    }

    private function resolvedStatusName(): string
    {
        $this->loadMissing('items', 'applicationStatus');

        if ($this->items->isEmpty()) {
            return ApplicationStatus::NAME_PENDING;
        }

        if ($this->needsBoilerChiefReviewBeforeManagement()) {
            return ApplicationStatus::NAME_PENDING;
        }

        $checkedCount = $this->items->where('is_checked', true)->count();
        $totalCount = $this->items->count();
        $rejectedWithReasonCount = $this->items->filter(
            fn (ApplicationItem $i) => ! (bool) $i->is_checked && trim((string) ($i->reason_not_selected ?? '')) !== ''
        )->count();
        $resolvedCount = $checkedCount + $rejectedWithReasonCount;

        if ($resolvedCount === $totalCount) {
            return ApplicationStatus::NAME_APPROVED;
        }

        if ($checkedCount === 0) {
            if (
                Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id)
                && ! $this->needsBoilerChiefReviewBeforeManagement()
            ) {
                $hasMgmtReason = $this->items->contains(
                    fn (ApplicationItem $i) => trim((string) ($i->reason_not_selected ?? '')) !== ''
                );
                if (! $hasMgmtReason) {
                    return ApplicationStatus::NAME_PENDING;
                }
            }

            return ApplicationStatus::NAME_REJECTED;
        }

        return ApplicationStatus::NAME_PARTIAL;
    }

    /**
     * Заявка мастера участка по подразделению с начальником котельной, ещё не отправлена на согласование.
     */
    public function isForemanDraftBeforeBoilerChief(): bool
    {
        $this->loadMissing('user:id,role_id');
        if ((int) ($this->user?->role_id ?? 0) !== 4) {
            return false;
        }
        if (! Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id)) {
            return false;
        }

        return (int) $this->application_status_id === ApplicationStatus::idForDraft();
    }

    /**
     * Заявка начальника котельной, ещё не отправлена на согласование руководству / снабжению.
     */
    public function isBoilerChiefDraftBeforeManagement(): bool
    {
        $this->loadMissing('user:id,role_id');

        return (int) ($this->user?->role_id ?? 0) === 7
            && (int) $this->application_status_id === ApplicationStatus::idForDraft();
    }

    public function isCreatorDraftApplication(): bool
    {
        return $this->isForemanDraftBeforeBoilerChief() || $this->isBoilerChiefDraftBeforeManagement();
    }

    /**
     * Директор, ТД или начальник снабжения сохранили согласование по позициям.
     */
    public function managementHasSavedApproval(): bool
    {
        if ($this->approved_by_user_id === null) {
            return false;
        }
        $this->loadMissing('approvedBy:id,role_id');
        if (! in_array((int) ($this->approvedBy?->role_id ?? 0), User::MANAGEMENT_EDITOR_ROLE_IDS, true)) {
            return false;
        }
        if ($this->usesBoilerChiefSubdivisionWorkflow()) {
            return $this->management_supply_items_saved_at !== null;
        }

        return true;
    }

    /**
     * Мастер участка может редактировать заявку (черновик или подразделение без этапа котельной).
     */
    public function foremanCanEditApplication(): bool
    {
        if ($this->managementHasSavedApproval()) {
            return false;
        }
        $this->loadMissing('user:id,role_id');
        if ((int) ($this->user?->role_id ?? 0) !== 4) {
            return true;
        }
        if ($this->isStatusApproved()) {
            return false;
        }
        if (Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id)
            && ! $this->isForemanDraftBeforeBoilerChief()) {
            return false;
        }

        return true;
    }

    /**
     * Начальник котельной может редактировать свою заявку, пока она не отправлена на согласование руководству.
     */
    public function boilerChiefCanEditApplication(): bool
    {
        if ($this->managementHasSavedApproval()) {
            return false;
        }
        $this->loadMissing('user:id,role_id');
        $creatorRoleId = (int) ($this->user?->role_id ?? 0);
        if ($creatorRoleId !== 7) {
            if (! $this->usesBoilerChiefSubdivisionWorkflow()) {
                return false;
            }
            if ($this->boilerChiefReleasedToManagement()) {
                return false;
            }

            return $this->needsBoilerChiefReviewBeforeManagement();
        }
        if ($this->isStatusApproved()) {
            return false;
        }
        if (! $this->isBoilerChiefDraftBeforeManagement()) {
            return false;
        }

        return true;
    }

    /**
     * По подразделению назначен начальник котельной, но этап его согласования ещё не завершён.
     */
    public function needsBoilerChiefReviewBeforeManagement(): bool
    {
        if ($this->isForemanDraftBeforeBoilerChief() || $this->isBoilerChiefDraftBeforeManagement()) {
            return false;
        }
        if (! Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id)) {
            return false;
        }
        $this->loadMissing('items');
        if ($this->items->isEmpty()) {
            return true;
        }

        return $this->items->contains(
            fn (ApplicationItem $i) => ! (bool) $i->is_checked && trim((string) ($i->reason_not_selected ?? '')) === ''
        );
    }

    public function usesBoilerChiefSubdivisionWorkflow(): bool
    {
        return Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id);
    }

    /**
     * Заявка мастера передана руководству и снабжению (после «Отправить на согласование» начальником котельной).
     */
    public function boilerChiefReleasedToManagement(): bool
    {
        if (! $this->usesBoilerChiefSubdivisionWorkflow()) {
            return true;
        }
        if (! $this->isForemanCreatedApplication()) {
            return true;
        }

        return $this->approved_by_user_id !== null;
    }

    /**
     * Начальник котельной может отправить заявку директору / ТД / снабжению (свой черновик или после согласования заявки мастера).
     */
    public function boilerChiefCanSubmitToManagement(): bool
    {
        if ($this->archived_at !== null) {
            return false;
        }
        if (! $this->usesBoilerChiefSubdivisionWorkflow()) {
            return false;
        }
        if ($this->isBoilerChiefDraftBeforeManagement()) {
            return $this->items()->exists();
        }
        if (! $this->isForemanCreatedApplication()) {
            return false;
        }
        if ($this->needsBoilerChiefReviewBeforeManagement() || $this->boilerChiefReleasedToManagement()) {
            return false;
        }

        return $this->items()->exists();
    }

    /**
     * Мастер участка или начальник котельной должен отправить заявку на следующий этап согласования.
     */
    public function needsSubmitToApprovalBy(?User $user): bool
    {
        if ($user === null || $this->archived_at !== null) {
            return false;
        }
        if ($user->hasRoleId(4)) {
            return $this->isForemanDraftBeforeBoilerChief();
        }
        if ($user->hasRoleId(7)) {
            return $this->boilerChiefCanSubmitToManagement();
        }

        return false;
    }

    /**
     * Руководство и снабжение могут согласовывать позиции после этапа котельной.
     */
    public function managementMayReviewAfterBoilerChief(): bool
    {
        if (! $this->usesBoilerChiefSubdivisionWorkflow()) {
            return true;
        }
        if ($this->needsBoilerChiefReviewBeforeManagement()) {
            return false;
        }

        return $this->boilerChiefReleasedToManagement();
    }

    /**
     * Этап котельной пройден, ожидается согласование позиций директором / ТД / снабжением (ещё ни одной позиции не отмечено).
     */
    public function awaitsManagementEquipmentApproval(): bool
    {
        if (! Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id)) {
            return false;
        }
        if ($this->needsBoilerChiefReviewBeforeManagement()) {
            return false;
        }
        if (! $this->boilerChiefReleasedToManagement()) {
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

    /**
     * Снабжение (директор / ТД / нач. снабжения) сохранило согласование по позициям настолько,
     * что можно открывать «Своё оборудование к заказу» и вести заказ (в т.ч. после этапа котельной).
     */
    public function isSupplyApprovedForCustomEquipmentWorkflow(): bool
    {
        if ($this->approved_by_user_id === null) {
            return false;
        }
        if (! Schema::hasTable('boiler_chief_subdivision_user')) {
            return true;
        }
        if (! Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id)) {
            return true;
        }
        if (! Schema::hasColumn('applications', 'management_supply_items_saved_at')) {
            return false;
        }

        return $this->management_supply_items_saved_at !== null;
    }

    /**
     * @param  Builder<Application>  $query
     */
    public function scopeWhereSupplyApprovedForCustomEquipmentWorkflow(Builder $query): void
    {
        $query->whereNotNull('approved_by_user_id');
        if (! Schema::hasTable('boiler_chief_subdivision_user')) {
            return;
        }
        $query->where(function (Builder $w): void {
            $w->whereNotExists(function ($e): void {
                $e->from('boiler_chief_subdivision_user')
                    ->whereColumn('boiler_chief_subdivision_user.subdivision_id', 'applications.subdivision_id');
            });
            if (Schema::hasColumn('applications', 'management_supply_items_saved_at')) {
                $w->orWhereNotNull('management_supply_items_saved_at');
            }
        });
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

    /**
     * Статус заявки и поле причин после сохранения согласования по позициям.
     *
     * @param  Collection<int, ApplicationItem>  $items
     * @return array{application_status_id: int, reason_for_refusal: string|null}
     */
    public static function aggregateApprovalPayloadFromItems(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [
                'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING),
                'reason_for_refusal' => null,
            ];
        }

        $checked = $items->where('is_checked', true)->count();
        $total = $items->count();
        $rejectedWithReasonCount = $items->filter(
            fn (ApplicationItem $i) => ! (bool) $i->is_checked && trim((string) ($i->reason_not_selected ?? '')) !== ''
        )->count();
        $resolvedCount = $checked + $rejectedWithReasonCount;
        $approvedId = ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED);
        $rejectedId = ApplicationStatus::idFor(ApplicationStatus::NAME_REJECTED);
        $partialId = ApplicationStatus::idFor(ApplicationStatus::NAME_PARTIAL);

        if ($resolvedCount === $total) {
            $lines = $items
                ->filter(fn (ApplicationItem $i) => ! (bool) $i->is_checked)
                ->map(fn (ApplicationItem $i) => trim((string) ($i->reason_not_selected ?? '')))
                ->filter()
                ->unique()
                ->values();

            return [
                'application_status_id' => $approvedId,
                'reason_for_refusal' => $lines->take(5)->implode('; ') ?: null,
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
                'reason_for_refusal' => $summary !== '' ? $summary : null,
            ];
        }

        return [
            'application_status_id' => $partialId,
            'reason_for_refusal' => null,
        ];
    }

    /**
     * Заявки в списке / в селекторах для мастера участка: только в назначенных ему подразделениях и
     * закреплённые за ним как ответственный (в том числе после переназначения с другого мастера).
     * Если ответственный не задан (старые данные), видна заявка только если её автор — этот мастер.
     *
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeForSiteForemanAccess(Builder $query, User $foreman): Builder
    {
        $assignedSubdivisionIds = $foreman->assignedSubdivisions()
            ->pluck('subdivisions.id')
            ->map(fn ($id): int => (int) $id);

        return $query
            ->whereIn('subdivision_id', $assignedSubdivisionIds)
            ->where(function (Builder $outer) use ($foreman): void {
                $outer->where('responsible_user_id', $foreman->id)
                    ->orWhere(function (Builder $inner) use ($foreman): void {
                        $inner->whereNull('responsible_user_id')
                            ->where('user_id', $foreman->id);
                    });
            });
    }

    /** Может ли мастер участка открыть заявку (согласовано с {@see scopeForSiteForemanAccess}). */
    public function isVisibleToSiteForeman(User $foreman): bool
    {
        $ids = $foreman->assignedSubdivisions()->pluck('subdivisions.id');
        if (! $ids->contains((int) $this->subdivision_id)) {
            return false;
        }

        if ($this->responsible_user_id !== null && (int) $this->responsible_user_id === (int) $foreman->id) {
            return true;
        }

        return $this->responsible_user_id === null && (int) $this->user_id === (int) $foreman->id;
    }

    /**
     * Та же выборка, что и в списке заявок: поиск и фильтр по статусу согласования.
     *
     * @return Builder<Application>
     */
    public static function listingQuery(Request $request): Builder
    {
        $search = trim((string) $request->input('q', ''));
        $approvalFilter = \App\Support\ApplicationApprovalListingFilter::normalize(
            $request->input('approval_filter', $request->input('equipment_filter', 'all'))
        );

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

        \App\Support\ApplicationApprovalListingFilter::apply($applications, $approvalFilter);

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

        $issueTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);

        return (float) MaterialStockMovement::query()
            ->where('material_stock_movement_type_id', $issueTypeId)
            ->where('equipment_id', (int) $item->equipment_id)
            ->whereCorrelationKey($base)
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

        if (! filled(trim((string) ($this->act_of_installation ?? '')))) {
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
            ->where('name', ApplicationStatus::NAME_COMPLETED)
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

        return $this->applicationStatus?->name === ApplicationStatus::NAME_COMPLETED;
    }

    public static function archiveFilterFromRequest(Request $request): string
    {
        if ($request->has('archive')) {
            $value = trim((string) $request->input('archive'));

            return in_array($value, ['active', 'archived', 'all'], true) ? $value : 'active';
        }

        $user = $request->user();

        return ($user instanceof User && $user->hasRoleId(User::ACCOUNTANT_ROLE_ID)) ? 'all' : 'active';
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
