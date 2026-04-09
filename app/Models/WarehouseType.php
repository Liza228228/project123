<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseType extends Model
{
    protected $fillable = ['name'];

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'warehouse_type_id');
    }
}
