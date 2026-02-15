<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailPriceType extends Model
{
    protected $fillable = ['name'];

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'retail_price_type_id');
    }
}
