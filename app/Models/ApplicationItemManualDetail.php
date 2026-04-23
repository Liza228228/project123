<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationItemManualDetail extends Model
{
    protected $fillable = [
        'application_item_id',
        'equipment_name',
        'base_name',
        'size_value',
        'measurement_type',
        'quantity_unit',
        'raw_input',
    ];

    public function applicationItem(): BelongsTo
    {
        return $this->belongsTo(ApplicationItem::class, 'application_item_id');
    }
}
