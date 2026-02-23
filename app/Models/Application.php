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
        'desired_delivery_date',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'desired_delivery_date' => 'date',
        ];
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

    /** Краткое отображение позиций: «Позиция 1, Позиция 2» или одна строка */
    public function getEquipmentSummaryAttribute(): string
    {
        $names = $this->items->map(fn (ApplicationItem $item) => $item->equipment_display_name.' × '.$item->quantity);

        return $names->isEmpty() ? '—' : $names->implode('; ');
    }
}
