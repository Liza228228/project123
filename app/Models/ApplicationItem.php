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
        'base_name',
        'size_value',
        'quantity',
        'measurement_type',
        'quantity_unit',
        'raw_input',
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
        $baseName = trim((string) ($this->base_name ?? ''));
        $size = trim((string) ($this->size_value ?? ''));
        if ($baseName !== '') {
            return trim($baseName.($size !== '' ? ' '.$size : ''));
        }

        if ($this->equipment_id && $this->equipment) {
            return $this->equipment->name;
        }

        return trim($this->equipment_name ?? '') ?: '—';
    }

    public function getQuantityWithUnitAttribute(): string
    {
        $unit = trim((string) ($this->quantity_unit ?? '')) ?: 'шт';

        return ((int) $this->quantity).' '.$unit;
    }
}
