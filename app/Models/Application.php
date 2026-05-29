<?php

namespace App\Models;

use App\Models\Scopes\ActiveApplicationItemScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Application extends Model
{
    protected $fillable = [
        'subdivision_id',
        'responsible_user_id',
        'act_of_installation',
        'desired_delivery_date',
        'approved_by_user_id',
        'management_supply_items_saved_at',
        'user_id',
        'source_application_id',
        'transport_option_id',
        'application_status_id',
        'reason_for_refusal',
    ];

    protected function casts(): array
    {
        return [
            'desired_delivery_date' => 'date',
            'management_supply_items_saved_at' => 'datetime',
        ];
    }

    public function archive(): HasOne
    {
        return $this->hasOne(ApplicationArchive::class);
    }

    public function getArchivedAtAttribute(): ?Carbon
    {
        $this->loadMissing('archive');

        return $this->archive?->archived_at;
    }

    public function getAdminArchivedAtAttribute(): ?Carbon
    {
        $this->loadMissing('archive');

        return $this->archive?->admin_archived_at;
    }

    public static function usesArchiveTable(): bool
    {
        return Schema::hasTable('application_archives');
    }

    /**
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        if (! static::usesArchiveTable()) {
            return $query->whereNull('archived_at');
        }

        return $query->whereDoesntHave('archive');
    }

    /**
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeArchived(Builder $query): Builder
    {
        if (! static::usesArchiveTable()) {
            return $query->whereNotNull('archived_at');
        }

        return $query->whereHas('archive');
    }

    public function isAdminArchived(): bool
    {
        if (! static::usesArchiveTable()) {
            return false;
        }

        $this->loadMissing('archive');

        return $this->archive?->admin_archived_at !== null;
    }

    public function isForemanCreatedApplication(): bool
    {
        $this->loadMissing('user:id,role_id');

        return (int) ($this->user?->role_id ?? 0) === 4;
    }

    public function isBoilerChiefCreatedApplication(): bool
    {
        $this->loadMissing('user:id,role_id');

        return (int) ($this->user?->role_id ?? 0) === 7;
    }

    /** Заявку создали директор, технический директор или начальник отдела снабжения. */
    public function isManagementCreatedApplication(): bool
    {
        $this->loadMissing('user:id,role_id');

        return in_array((int) ($this->user?->role_id ?? 0), User::MANAGEMENT_EDITOR_ROLE_IDS, true);
    }

    /**
     * Руководство создало заявку и назначило ответственным мастера участка:
     * мастер только просматривает, согласование котельной и руководства не требуется.
     */
    public function isManagementDelegatedToSiteForeman(): bool
    {
        if (! $this->isManagementCreatedApplication()) {
            return false;
        }

        $responsibleId = (int) ($this->responsible_user_id ?? 0);
        if ($responsibleId <= 0 || $responsibleId === (int) $this->user_id) {
            return false;
        }

        $this->loadMissing('responsibleUser:id,role_id');

        return (int) ($this->responsibleUser?->role_id ?? 0) === 4;
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

    /**
     * Позиции, снятые с заявки при редактировании (причина — для мастера участка).
     *
     * @return HasMany<ApplicationItem, $this>
     */
    public function removedItems(): HasMany
    {
        return $this->hasMany(ApplicationItem::class)
            ->withoutGlobalScope(ActiveApplicationItemScope::class)
            ->whereNotNull('removed_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<ApplicationChangeJournal, $this>
     */
    public function changeJournalEntries(): HasMany
    {
        return $this->hasMany(ApplicationChangeJournal::class)->orderByDesc('created_at');
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
     * Сводка по ожидаемому прибытию для позиций каталога в статусе «В пути».
     */
    public function expectedArrivalSummaryLine(): ?string
    {
        $this->loadMissing('items');

        $times = $this->items
            ->filter(fn (ApplicationItem $item) => $item->resolvedDeliveryStatus() === ApplicationItem::DELIVERY_IN_TRANSIT)
            ->map(fn (ApplicationItem $item) => $item->expected_arrival_at)
            ->filter()
            ->sort()
            ->values();

        if ($times->isEmpty()) {
            return null;
        }

        if ($times->count() === 1) {
            return $times->first()->format('d.m.Y');
        }

        return $times->first()->format('d.m.Y').' — '.$times->last()->format('d.m.Y');
    }

    /**
     * Все согласованные позиции находятся в «В пути» (поставка своего оборудования или доставка на объект).
     */
    public function isApprovedDeliveryFullyInTransit(): bool
    {
        if ($this->isArchived()) {
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
        if ($this->needsBoilerChiefReviewBeforeManagement() || $this->isPendingManagementReview()) {
            return false;
        }

        return $this->resolvedStatusName() === ApplicationStatus::NAME_APPROVED;
    }

    public function isStatusRejected(): bool
    {
        if ($this->needsBoilerChiefReviewBeforeManagement() || $this->isPendingManagementReview()) {
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
        if ($this->needsBoilerChiefReviewBeforeManagement() || $this->isPendingManagementReview()) {
            return false;
        }

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

        if ($this->isPendingManagementReview()) {
            return ApplicationStatus::NAME_PENDING;
        }

        $checkedCount = $this->items->where('is_checked', true)->count();
        $totalCount = $this->items->count();
        $rejectedWithReasonCount = $this->items->filter(
            fn (ApplicationItem $i) => ! (bool) $i->is_checked && trim((string) ($i->reason_not_selected ?? '')) !== ''
        )->count();
        $resolvedCount = $checkedCount + $rejectedWithReasonCount;

        if ($resolvedCount === $totalCount) {
            return self::resolvedEquipmentLinesStatusWhenAllResolved($checkedCount, $totalCount);
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
        if ((int) $this->application_status_id !== ApplicationStatus::idForDraft()) {
            return false;
        }

        return ! $this->boilerChiefSubdivisionReviewCycleStarted();
    }

    /**
     * Черновик заявки мастера после согласования котельной, до отправки руководству.
     */
    public function isForemanDraftAfterBoilerChiefBeforeManagement(): bool
    {
        $this->loadMissing('user:id,role_id');
        if ((int) ($this->user?->role_id ?? 0) !== 4) {
            return false;
        }
        if (! $this->usesBoilerChiefSubdivisionWorkflow()) {
            return false;
        }
        if ((int) $this->application_status_id !== ApplicationStatus::idForDraft()) {
            return false;
        }
        if ($this->needsBoilerChiefReviewBeforeManagement()) {
            return false;
        }
        if ($this->boilerChiefReleasedToManagement()) {
            return false;
        }

        return $this->boilerChiefSubdivisionReviewCycleStarted();
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
     * Черновик на любом этапе до следующей отправки на согласование (для отображения в списке).
     */
    public function isWorkflowDraftForDisplay(): bool
    {
        return $this->isForemanDraftBeforeBoilerChief()
            || $this->isBoilerChiefDraftBeforeManagement()
            || $this->isForemanDraftAfterBoilerChiefBeforeManagement();
    }

    /**
     * Заявка у руководства / снабжения: котельная отправила, согласование руководства ещё не сохранено.
     */
    public function isPendingManagementReview(): bool
    {
        if ($this->isWorkflowDraftForDisplay()) {
            return false;
        }
        if ($this->needsBoilerChiefReviewBeforeManagement()) {
            return false;
        }
        if (! $this->usesBoilerChiefSubdivisionWorkflow()) {
            return false;
        }
        if (! $this->boilerChiefReleasedToManagement()) {
            return false;
        }

        return ! $this->managementHasSavedApproval();
    }

    /**
     * Директор, ТД или начальник снабжения могут править заявку после передачи от котельной
     * до сохранения согласования по позициям (только подразделения с закреплённой котельной).
     */
    public function managementCanEditApplication(): bool
    {
        if ($this->archived_at !== null || $this->isAdminArchived()) {
            return false;
        }
        if ($this->managementHasSavedApproval()) {
            return false;
        }
        if (! Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id)) {
            return false;
        }
        if (! $this->managementMayReviewAfterBoilerChief()) {
            return false;
        }
        $this->loadMissing('items');

        return ! $this->items->contains(
            fn (ApplicationItem $i) => in_array($i->resolvedDeliveryStatus(), [
                ApplicationItem::DELIVERY_IN_TRANSIT,
                ApplicationItem::DELIVERY_DELIVERED,
            ], true)
        );
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

        if ($this->items->isNotEmpty() && ! $this->items->contains(fn (ApplicationItem $i) => (bool) $i->is_checked)) {
            return false;
        }

        if ($this->usesBoilerChiefSubdivisionWorkflow()) {
            return $this->management_supply_items_saved_at !== null;
        }

        return true;
    }

    /**
     * Итоговый статус заявки, когда по каждой позиции уже есть решение (согласована или отклонена с причиной).
     */
    public static function resolvedEquipmentLinesStatusWhenAllResolved(int $checkedCount, int $totalCount): string
    {
        if ($checkedCount === 0) {
            return ApplicationStatus::NAME_REJECTED;
        }

        if ($checkedCount === $totalCount) {
            return ApplicationStatus::NAME_APPROVED;
        }

        return ApplicationStatus::NAME_PARTIAL;
    }

    public function hasApprovedEquipmentLines(): bool
    {
        $this->loadMissing('items');

        return $this->items->contains(fn (ApplicationItem $i) => (bool) $i->is_checked);
    }

    /**
     * Мастер участка может редактировать заявку: свой черновик или подразделение без котельной.
     * Заявки, созданные начальником котельной, мастеру только для просмотра.
     */
    public function foremanCanEditApplication(): bool
    {
        if ($this->isAdminArchived()) {
            return false;
        }

        if ($this->managementHasSavedApproval()) {
            return false;
        }

        if ($this->isManagementDelegatedToSiteForeman()) {
            return false;
        }

        if ($this->isBoilerChiefCreatedApplication()) {
            return false;
        }

        if (! $this->isForemanCreatedApplication()) {
            return false;
        }

        if ($this->isStatusApproved()) {
            return false;
        }

        if (Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id)
            && ! $this->isForemanDraftBeforeBoilerChief()) {
            return $this->foremanCanReviseAfterBoilerChiefRejection();
        }

        return true;
    }

    /**
     * Мастер участка может править позиции, которые начальник котельной не согласовал
     * (после сохранения согласования котельной, до отправки заявки руководству).
     */
    public function foremanCanReviseAfterBoilerChiefRejection(): bool
    {
        if ($this->isAdminArchived() || $this->managementHasSavedApproval()) {
            return false;
        }

        if ($this->isManagementDelegatedToSiteForeman()) {
            return false;
        }

        if (! $this->isForemanCreatedApplication() || $this->isBoilerChiefCreatedApplication()) {
            return false;
        }

        if ($this->isForemanDraftBeforeBoilerChief()) {
            return false;
        }

        if (! $this->usesBoilerChiefSubdivisionWorkflow()) {
            return false;
        }

        if ($this->boilerChiefReleasedToManagement()) {
            return false;
        }

        if ($this->foremanSubmittedAwaitingItemsForBoilerChiefReview()) {
            return false;
        }

        if ($this->needsBoilerChiefReviewBeforeManagement() && ! $this->hasItemsRejectedByBoilerChief()) {
            return false;
        }

        return $this->hasItemsRejectedByBoilerChief();
    }

    /**
     * Мастер участка отправил изменённые позиции на повторное согласование — ждём решения начальника котельной.
     */
    public function foremanSubmittedAwaitingItemsForBoilerChiefReview(): bool
    {
        if (! $this->boilerChiefSubdivisionReviewCycleStarted()) {
            return false;
        }

        return $this->needsBoilerChiefReviewBeforeManagement()
            && $this->hasItemsAwaitingBoilerChiefReview()
            && (int) $this->application_status_id === ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING);
    }

    public function hasItemsRejectedByBoilerChief(): bool
    {
        $this->loadMissing('items');

        return $this->items->contains(fn (ApplicationItem $i) => $this->itemIsRejectedByBoilerChief($i));
    }

    public function itemIsRejectedByBoilerChief(ApplicationItem $item): bool
    {
        return ! (bool) $item->is_checked
            && trim((string) ($item->reason_not_selected ?? '')) !== '';
    }

    /**
     * Позиция снова ожидает решения начальника котельной (мастер изменил и отправил на повторное согласование).
     */
    public function itemAwaitingBoilerChiefReview(ApplicationItem $item): bool
    {
        return ! (bool) $item->is_checked
            && trim((string) ($item->reason_not_selected ?? '')) === '';
    }

    public function hasItemsAwaitingBoilerChiefReview(): bool
    {
        $this->loadMissing('items');

        return $this->items->contains(fn (ApplicationItem $i) => $this->itemAwaitingBoilerChiefReview($i));
    }

    /**
     * @return \Illuminate\Support\Collection<int, ApplicationItem>
     */
    public function itemsAwaitingBoilerChiefReview()
    {
        $this->loadMissing('items');

        return $this->items
            ->filter(fn (ApplicationItem $i) => $this->itemAwaitingBoilerChiefReview($i))
            ->values();
    }

    public function boilerChiefSubdivisionReviewCycleStarted(): bool
    {
        if ($this->hasApprovedEquipmentLines() || $this->hasItemsRejectedByBoilerChief()) {
            return true;
        }

        return false;
    }

    /**
     * Мастер участка может повторно отправить заявку начальнику котельной после правок.
     */
    public function foremanCanResubmitAwaitingItemsToBoilerChief(): bool
    {
        if ($this->isAdminArchived() || $this->isForemanDraftBeforeBoilerChief()) {
            return false;
        }

        if (! $this->isForemanCreatedApplication() || $this->isBoilerChiefCreatedApplication()) {
            return false;
        }

        if (! $this->usesBoilerChiefSubdivisionWorkflow() || $this->boilerChiefReleasedToManagement()) {
            return false;
        }

        if ($this->foremanSubmittedAwaitingItemsForBoilerChiefReview()) {
            return false;
        }

        return $this->hasItemsAwaitingBoilerChiefReview()
            && $this->boilerChiefSubdivisionReviewCycleStarted();
    }

    /**
     * Начальник котельной может редактировать свою заявку, пока она не отправлена на согласование руководству.
     */
    public function boilerChiefCanEditApplication(): bool
    {
        if ($this->isAdminArchived()) {
            return false;
        }

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
        if ($this->isBoilerChiefCreatedApplication()) {
            return false;
        }
        if ($this->isManagementCreatedApplication()) {
            return false;
        }
        if (! Subdivision::hasBoilerChiefAssigned((int) $this->subdivision_id)) {
            return false;
        }
        $this->loadMissing('items');

        if ($this->items->isEmpty()) {
            return true;
        }

        if ($this->boilerChiefReleasedToManagement()) {
            return false;
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
     * Заявка попадает в список у директора, ТД и начальника снабжения
     * (согласовано с {@see scopeVisibleToManagementEditors}).
     */
    public function isVisibleToManagementEditors(): bool
    {
        if (! $this->isForemanCreatedApplication()) {
            return true;
        }
        if (! $this->usesBoilerChiefSubdivisionWorkflow()) {
            return true;
        }

        return $this->approved_by_user_id !== null;
    }

    /**
     * Список заявок для директора, ТД и начальника снабжения: без черновиков мастера «у котельной»
     * и без заявок мастера, которые котельная ещё не передала руководству.
     *
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeVisibleToManagementEditors(Builder $query): Builder
    {
        if (! Schema::hasTable('boiler_chief_subdivision_user')) {
            return $query->where(function (Builder $outer): void {
                $outer->whereDoesntHave('user', function (Builder $userQuery): void {
                    $userQuery->where('role_id', 4);
                })->orWhereNotNull('approved_by_user_id');
            });
        }

        return $query->where(function (Builder $outer): void {
            $outer->whereDoesntHave('user', function (Builder $userQuery): void {
                $userQuery->where('role_id', 4);
            })->orWhere(function (Builder $foremanPath): void {
                $foremanPath
                    ->whereHas('user', function (Builder $userQuery): void {
                        $userQuery->where('role_id', 4);
                    })
                    ->where(function (Builder $released): void {
                        $released
                            ->whereNotNull('approved_by_user_id')
                            ->orWhereNotExists(function ($sub): void {
                                $sub->selectRaw('1')
                                    ->from('boiler_chief_subdivision_user')
                                    ->whereColumn(
                                        'boiler_chief_subdivision_user.subdivision_id',
                                        'applications.subdivision_id'
                                    );
                            });
                    });
            });
        });
    }

    /**
     * Начальник котельной может отправить заявку директору / ТД / снабжению (свой черновик или после согласования заявки мастера).
     */
    public function boilerChiefCanSubmitToManagement(): bool
    {
        if ($this->isArchived() || $this->isStatusRejected()) {
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
        if (! $this->hasApprovedEquipmentLines()) {
            return false;
        }

        return $this->items()->exists();
    }

    /**
     * Мастер участка или начальник котельной должен отправить заявку на следующий этап согласования.
     */
    public function needsSubmitToApprovalBy(?User $user): bool
    {
        if ($user === null || $this->isArchived() || $this->isStatusRejected()) {
            return false;
        }
        if ($user->hasRoleId(4)) {
            return $this->isForemanDraftBeforeBoilerChief()
                || $this->foremanCanResubmitAwaitingItemsToBoilerChief();
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
        if ($this->isManagementDelegatedToSiteForeman()) {
            return false;
        }
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
        if ($this->isManagementDelegatedToSiteForeman()) {
            return false;
        }
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

    /**
     * Согласование блокируется, если по заявке уже есть оборудование в доставке или на складе получателя.
     */
    public function approvalLockedByShipmentProgress(): bool
    {
        $this->loadMissing('items');

        return $this->items->contains(function (ApplicationItem $item): bool {
            if (in_array(
                $item->resolvedDeliveryStatus(),
                [ApplicationItem::DELIVERY_IN_TRANSIT, ApplicationItem::DELIVERY_DELIVERED],
                true
            )) {
                return true;
            }

            if ($item->usesFreeTextEquipment()) {
                return in_array(
                    $item->resolvedCustomSupplyStatus(),
                    [ApplicationItem::CUSTOM_SUPPLY_IN_TRANSIT, ApplicationItem::CUSTOM_SUPPLY_ON_WAREHOUSE],
                    true
                );
            }

            return false;
        });
    }

    public function hasPendingCustomEquipmentProcurementLines(): bool
    {
        $this->loadMissing('items');

        return $this->items->contains(
            fn (ApplicationItem $item) => $item->canMarkCustomSupplyOrdered()
                || $item->canMarkCustomSupplyOnWarehouse()
                || $item->canMarkCustomSupplyInTransit()
        );
    }

    public function itemLineIsApproved(int $itemId): bool
    {
        $this->loadMissing('items');

        return (bool) $this->items->firstWhere('id', $itemId)?->is_checked;
    }

    public function itemLineRejectionReason(int $itemId): ?string
    {
        $this->loadMissing('items');
        $item = $this->items->firstWhere('id', $itemId);
        $r = $item?->reason_not_selected;

        $r = $r !== null ? trim((string) $r) : '';

        return $r !== '' ? $r : null;
    }

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
            $statusName = self::resolvedEquipmentLinesStatusWhenAllResolved($checked, $total);

            return [
                'application_status_id' => ApplicationStatus::idFor($statusName),
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

    /**
     * Заявки с хотя бы одной согласованной позицией, по которой оборудование уже на складе.
     *
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeEligibleForReportEquipmentInsertion(Builder $query): Builder
    {
        return $query->whereHas('items', function (Builder $items): void {
            $items->where('is_checked', true)
                ->where(function (Builder $row): void {
                    $row->where(function (Builder $catalog): void {
                        $catalog->whereNotNull('equipment_id')
                            ->where('delivery_status_id', ApplicationItem::DELIVERY_DELIVERED_ID)
                            ->where('delivery_warehouse_id', '>', 0);
                    })->orWhere(function (Builder $custom): void {
                        $custom->whereNull('equipment_id')
                            ->where(
                                'custom_equipment_supply_status_id',
                                ApplicationItem::CUSTOM_SUPPLY_ON_WAREHOUSE_ID
                            );
                    });
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
        if (static::usesArchiveTable()) {
            $this->loadMissing('archive');

            return $this->archive !== null;
        }

        return ($this->attributes['archived_at'] ?? null) !== null;
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
     * Согласованные позиции, по которым перед актом установки оборудование должно быть на складе получателя.
     *
     * @return \Illuminate\Support\Collection<int, ApplicationItem>
     */
    public function checkedItemsRequiringDeliveryForInstallationAct(): \Illuminate\Support\Collection
    {
        $this->loadMissing('items');

        return $this->items->filter(function (ApplicationItem $item): bool {
            if (! $item->is_checked) {
                return false;
            }

            if ($item->equipment_id !== null) {
                return true;
            }

            return $item->usesFreeTextEquipment();
        })->values();
    }

    /**
     * Каталожная позиция доставлена на склад подразделения-получателя (отметка «Доставлено»).
     */
    public function catalogItemDeliveredToRecipientWarehouse(ApplicationItem $item): bool
    {
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

        return true;
    }

    /**
     * Можно прикрепить или заменить акт установки и фото: заявка полностью согласована, есть согласованные позиции
     * и по каждой из них оборудование уже на складе подразделения-получателя (каталог — «Доставлено», своё — «На складе»).
     */
    public function canUploadInstallationActAndPhotos(): bool
    {
        if (! $this->isStatusApproved()) {
            return false;
        }

        $requiredItems = $this->checkedItemsRequiringDeliveryForInstallationAct();
        if ($requiredItems->isEmpty()) {
            return false;
        }

        foreach ($requiredItems as $item) {
            if ($item->equipment_id !== null) {
                if (! $this->catalogItemDeliveredToRecipientWarehouse($item)) {
                    return false;
                }

                continue;
            }

            if ($item->resolvedCustomSupplyStatus() !== ApplicationItem::CUSTOM_SUPPLY_ON_WAREHOUSE) {
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
        if (! static::usesArchiveTable() && ! Schema::hasColumn('applications', 'archived_at')) {
            return false;
        }

        if ($this->isArchived()) {
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
        if (! static::usesArchiveTable() && ! Schema::hasColumn('applications', 'archived_at')) {
            return false;
        }

        if ($this->isArchived()) {
            return false;
        }

        if (! $this->qualifiesForCompletionArchive()) {
            return false;
        }

        $completedId = ApplicationStatus::query()
            ->where('name', ApplicationStatus::NAME_COMPLETED)
            ->value('id');

        $this->moveToArchive([], $completedId !== null ? (int) $completedId : null);

        return true;
    }

    /**
     * Заявка закрыта и перенесена в архив выполненных (или помечена статусом «Выполнена»).
     */
    public function isLifecycleCompleted(): bool
    {
        if ($this->isAdminArchived()) {
            return false;
        }

        if ($this->isArchived()) {
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

        if ($user instanceof User && $user->hasAnyRoleId([User::ACCOUNTANT_ROLE_ID, User::ADMINISTRATOR_ROLE_ID])) {
            return 'all';
        }

        return 'active';
    }

    /**
     * Принудительный перенос в архив (администратор, без проверки акта/списаний).
     */
    public function adminForceArchive(?int $archivedByUserId = null): void
    {
        if (! static::usesArchiveTable() && ! Schema::hasColumn('applications', 'archived_at')) {
            return;
        }

        if ($this->isArchived()) {
            return;
        }

        $now = Carbon::now();
        $this->moveToArchive([
            'archived_at' => $now,
            'admin_archived_at' => $now,
            'archived_by_user_id' => $archivedByUserId,
        ]);
    }

    /**
     * Возврат заявки из архива администратора (только помеченные admin_archived_at).
     */
    public function adminRestoreFromArchive(): bool
    {
        if (! static::usesArchiveTable()) {
            return false;
        }

        if (! $this->isAdminArchived()) {
            return false;
        }

        $this->archive?->delete();
        $this->unsetRelation('archive');

        return true;
    }

    /**
     * @param  array<string, mixed>  $archiveAttributes
     */
    public function moveToArchive(array $archiveAttributes = [], ?int $applicationStatusId = null): void
    {
        if ($this->isArchived()) {
            return;
        }

        if (static::usesArchiveTable()) {
            ApplicationArchive::query()->create(array_merge([
                'application_id' => (int) $this->id,
                'archived_at' => Carbon::now(),
            ], $archiveAttributes));
            $this->unsetRelation('archive');
        } elseif (Schema::hasColumn('applications', 'archived_at')) {
            $payload = array_merge(['archived_at' => Carbon::now()], $archiveAttributes);
            $this->forceFill($payload)->save();
        }

        if ($applicationStatusId !== null) {
            $this->forceFill(['application_status_id' => $applicationStatusId])->save();
        }
    }

    /**
     * @param  Builder<Application>  $applications
     */
    public static function applyArchiveFilterToListingQuery(Builder $applications, string $archiveFilter): void
    {
        if (! static::usesArchiveTable() && ! Schema::hasColumn('applications', 'archived_at')) {
            return;
        }

        match ($archiveFilter) {
            'active' => $applications->notArchived(),
            'archived' => $applications->archived(),
            default => null,
        };
    }
}
