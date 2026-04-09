<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    protected $fillable = [
        'is_primary',
        'name',
        'code',
        'subdivision_id',
        'warehouse_type_id',
        'retail_price_type_id',
        'comment',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }

    public function warehouseType(): BelongsTo
    {
        return $this->belongsTo(WarehouseType::class, 'warehouse_type_id');
    }

    public function retailPriceType(): BelongsTo
    {
        return $this->belongsTo(RetailPriceType::class, 'retail_price_type_id');
    }
}
