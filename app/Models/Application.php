<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    protected $fillable = [
        'subdivision_id',
        'responsible_user_id',
        'equipment_in_warehouse',
        'commercial_offer_path',
        'desired_delivery_date',
        'approved_by_user_id',
        'user_id',
        'source_application_id',
        'transport_option_id',
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

    /**
     * Текстовые строки последней записи истории правок (по одной строке на запись в БД).
     *
     * @return list<string>
     */
    public function lastEditDetailLines(): array
    {
        $history = $this->relationLoaded('latestEditHistory')
            ? $this->getRelation('latestEditHistory')
            : $this->latestEditHistory()->with('lines')->first();

        if (! $history) {
            return [];
        }

        if ($history->relationLoaded('lines')) {
            return $history->lines->sortBy('sort_order')->pluck('body')->values()->all();
        }

        return $history->lineBodies();
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

    /** Краткое отображение позиций: «Позиция 1, Позиция 2» или одна строка */
    public function getEquipmentSummaryAttribute(): string
    {
        $names = $this->items->map(fn (ApplicationItem $item) => $item->equipment_display_name.' × '.$item->quantity);

        return $names->isEmpty() ? '—' : $names->implode('; ');
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function approvedEquipmentLineItems(): \Illuminate\Support\Collection
    {
        return $this->items
            ->where('is_checked', true)
            ->sortBy('id')
            ->values()
            ->map(fn (ApplicationItem $item) => $item->equipment_display_name.' × '.$item->quantity);
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function notApprovedEquipmentLineItems(): \Illuminate\Support\Collection
    {
        return $this->items
            ->where('is_checked', false)
            ->sortBy('id')
            ->values()
            ->map(fn (ApplicationItem $item) => $item->equipment_display_name.' × '.$item->quantity);
    }

    /**
     * По каждой позиции — галочка или указана причина отказа (заявка закрыта по согласованию).
     */
    public function getIsFullyApprovedAttribute(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }

        return $this->items->every(fn (ApplicationItem $item) => $item->is_checked
            || trim((string) ($item->reason_not_selected ?? '')) !== '');
    }
}
