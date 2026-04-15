<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeasurementUnit extends Model
{
    protected $fillable = [
        'unit_type_id',
        'code',
        'name',
        'is_base',
        'multiplier_to_base',
    ];

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'multiplier_to_base' => 'decimal:4',
        ];
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'measurement_unit_id');
    }
}
