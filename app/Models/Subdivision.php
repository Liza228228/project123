<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    public function boilerChiefUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'boiler_chief_subdivision_user', 'subdivision_id', 'boiler_chief_user_id')
            ->withPivot('assigned_by_user_id')
            ->withTimestamps();
    }

    public static function hasBoilerChiefAssigned(int $subdivisionId): bool
    {
        if ($subdivisionId <= 0 || ! Schema::hasTable('boiler_chief_subdivision_user')) {
            return false;
        }

        return DB::table('boiler_chief_subdivision_user')
            ->where('subdivision_id', $subdivisionId)
            ->exists();
    }
}
