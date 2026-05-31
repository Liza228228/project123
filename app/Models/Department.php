<?php

// модель
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
    ];

    public function requestLayouts(): HasMany
    {
        return $this->hasMany(RequestLayout::class, 'division_assigner_id');
    }
}
