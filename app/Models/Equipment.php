<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $fillable = ['name'];

    public function applicationItems(): HasMany
    {
        return $this->hasMany(ApplicationItem::class, 'equipment_id');
    }
}
