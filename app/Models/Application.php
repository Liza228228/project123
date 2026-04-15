<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Application extends Model
{
    protected $fillable = [
        'subdivision_id',
        'responsible_user_id',
        'commercial_offer_path',
        'desired_delivery_date',
        'approved_by_user_id',
        'user_id',
        'source_application_id',
        'transport_option_id',
        'application_status_id',
        'approval_rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'desired_delivery_date' => 'date',
        ];
    }

    public function editHistories(): HasMany
    {
        return $this->hasMany(ApplicationEditHistory::class)->orderByDesc('edited_at')->orderByDesc('id');
    }

    public function latestEditHistory(): HasOne
    {
        return $this->hasOne(ApplicationEditHistory::class)->latestOfMany('edited_at');
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
        return $this->resolvedStatusCode() === ApplicationStatus::CODE_APPROVED;
    }

    public function isStatusRejected(): bool
    {
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

        $checkedCount = $this->items->where('is_checked', true)->count();
        $totalCount = $this->items->count();

        if ($checkedCount === $totalCount) {
            return ApplicationStatus::CODE_APPROVED;
        }

        if ($checkedCount === 0) {
            return ApplicationStatus::CODE_REJECTED;
        }

        return ApplicationStatus::CODE_PARTIAL;
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
        $approvedId = ApplicationStatus::idFor(ApplicationStatus::CODE_APPROVED);
        $rejectedId = ApplicationStatus::idFor(ApplicationStatus::CODE_REJECTED);
        $partialId = ApplicationStatus::query()->where('code', ApplicationStatus::CODE_PARTIAL)->value('id');
        $partialId = $partialId !== null ? (int) $partialId : $rejectedId;

        if ($checked === $total) {
            return [
                'application_status_id' => $approvedId,
                'approval_rejection_reason' => null,
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
        $allowedFilters = ['all', 'has_approved', 'has_not_approved', 'fully_approved', 'on_approval'];
        if (! in_array($equipmentFilter, $allowedFilters, true)) {
            $equipmentFilter = 'all';
        }

        $applications = static::query();

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
                        $q->where('equipment_name', 'like', $like)
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
}
