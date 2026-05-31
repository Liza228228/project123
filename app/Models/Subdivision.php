<?php

// модель
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Relations\HasOne;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Schema;

class Subdivision extends Model

{

    protected $fillable = [

        'name',

    ];

    public function archive(): HasOne

    {

        return $this->hasOne(SubdivisionArchive::class);

    }

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

            ->withTimestamps();

    }

    public function boilerChiefUsers(): BelongsToMany

    {

        return $this->belongsToMany(User::class, 'boiler_chief_subdivision_user', 'subdivision_id', 'boiler_chief_user_id')

            ->withTimestamps();

    }

    public function isArchived(): bool

    {

        if (! Schema::hasTable('subdivision_archives')) {

            return false;

        }

        if ($this->relationLoaded('archive')) {

            return $this->archive !== null;

        }

        return $this->archive()->exists();

    }

    public function isActive(): bool

    {

        return ! $this->isArchived();

    }
    public function scopeActive(Builder $query): Builder

    {

        if (! Schema::hasTable('subdivision_archives')) {

            return $query;

        }

        return $query->whereDoesntHave('archive');

    }
    public function scopeArchived(Builder $query): Builder

    {

        if (! Schema::hasTable('subdivision_archives')) {

            return $query->whereRaw('1 = 0');

        }

        return $query->whereHas('archive');

    }
    private static ?array $subdivisionIdsWithBoilerChief = null;

    public static function hasBoilerChiefAssigned(int $subdivisionId): bool
    {
        if ($subdivisionId <= 0 || ! Schema::hasTable('boiler_chief_subdivision_user')) {
            return false;
        }

        if (self::$subdivisionIdsWithBoilerChief === null) {
            self::$subdivisionIdsWithBoilerChief = DB::table('boiler_chief_subdivision_user')
                ->distinct()
                ->pluck('subdivision_id')
                ->mapWithKeys(fn ($id): array => [(int) $id => true])
                ->all();
        }

        return isset(self::$subdivisionIdsWithBoilerChief[$subdivisionId]);
    }

    public static function resetBoilerChiefCache(): void
    {
        self::$subdivisionIdsWithBoilerChief = null;
    }

}

