<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationItem extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_checked' => false,
    ];

    protected $fillable = [
        'application_id',
        'equipment_id',
        'equipment_name',
        'quantity',
        'is_checked',
        'reason_not_selected',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_checked' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function getEquipmentDisplayNameAttribute(): string
    {
        if ($this->equipment_id && $this->equipment) {
            return $this->equipment->name;
        }

        return trim($this->equipment_name ?? '') ?: '—';
    }
}
