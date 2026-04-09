<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subdivision extends Model
{
    protected $fillable = ['name'];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class)->orderBy('name');
    }

    public function siteForemen(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'foreman_subdivision_user', 'subdivision_id', 'foreman_user_id')
            ->withPivot('assigned_by_user_id')
            ->withTimestamps();
    }
}
