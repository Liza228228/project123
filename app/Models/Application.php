<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    protected $fillable = [
        'subdivision_id',
        'responsible_user_id',
        'equipment_type_id',
        'equipment_name',
        'equipment_in_warehouse',
        'quantity',
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

    public function equipmentType(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Отображаемое название оборудования: из справочника или свободный ввод */
    public function getEquipmentDisplayNameAttribute(): string
    {
        if ($this->equipment_type_id && $this->equipmentType) {
            return $this->equipmentType->name;
        }
        return $this->equipment_name ?? '—';
    }
}
