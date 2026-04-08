<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    protected $fillable = [
        'subdivision_id',
        'responsible_user_id',
        'equipment_in_warehouse',
        'commercial_offer_path',
        'desired_delivery_date',
        'approved_at',
        'user_id',
        'source_application_id',
        'transport_option_id',
        'director_last_edited_at',
        'director_last_edited_by',
        'director_last_edit_detail',
    ];

    protected function casts(): array
    {
        return [
            'desired_delivery_date' => 'date',
            'approved_at' => 'datetime',
            'director_last_edited_at' => 'datetime',
        ];
    }

    /**
     * Строки «что изменил директор» из многострочного text-поля.
     *
     * @return list<string>
     */
    public function directorLastEditDetailLines(): array
    {
        $raw = trim((string) ($this->director_last_edit_detail ?? ''));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/\r\n|\n|\r/', $raw)));
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

    public function items(): HasMany
    {
        return $this->hasMany(ApplicationItem::class)->orderBy('id');
    }

    public function directorLastEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_last_edited_by');
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
     * Согласование завершено: после успешного «Сохранить согласование» (по каждой позиции — галочка или причина отказа).
     */
    public function getIsFullyApprovedAttribute(): bool
    {
        if ($this->approved_at === null || $this->items->isEmpty()) {
            return false;
        }

        return $this->items->every(fn (ApplicationItem $item) => $item->is_checked
            || trim((string) ($item->reason_not_selected ?? '')) !== '');
    }
}
