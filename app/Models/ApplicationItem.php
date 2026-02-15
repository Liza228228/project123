<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationItem extends Model
{
    protected $fillable = [
        'application_id',
        'equipment_type_id',
        'equipment_name',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function equipmentType(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class);
    }

    public function getEquipmentDisplayNameAttribute(): string
    {
        if ($this->equipment_type_id && $this->equipmentType) {
            return $this->equipmentType->name;
        }
        return trim($this->equipment_name ?? '') ?: '—';
    }
}
