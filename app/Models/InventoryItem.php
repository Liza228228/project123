<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = [
        'parent_id',
        'is_group',
        'name',
        'code',
        'warehouse_type_id',
        'comment',
    ];

    protected function casts(): array
    {
        return ['is_group' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'parent_id');
    }

    public function warehouseType(): BelongsTo
    {
        return $this->belongsTo(WarehouseType::class, 'warehouse_type_id');
    }
}
