<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /** Директор, технический директор, начальник снабжения — согласование заявок, списания, материалы и т.д. */
    public const MANAGEMENT_EDITOR_ROLE_IDS = [1, 6, 2];

    /** Директор и начальник снабжения — заказ нестандартного («своего») оборудования и приход на основной склад по нему. */
    public const CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS = [1, 2];

    /** Списание со склада «Администрация» по согласованным позициям из справочника. */
    public const ISSUE_STOCK_FROM_MAIN_ROLE_IDS = [1, 2, 6];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'surname',
        'name',
        'patronymic',
        'email',
        'password',
        'role_id',
        'is_blocked',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_blocked' => 'boolean',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function requestLayouts(): HasMany
    {
        return $this->hasMany(RequestLayout::class, 'user_assigner_id');
    }

    public function documentHeaderLayouts(): HasMany
    {
        return $this->hasMany(DocumentHeaderLayout::class, 'user_assigner_id');
    }

    public function layoutApplications(): HasMany
    {
        return $this->hasMany(RequestSubmission::class, 'created_by');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function assignedSubdivisions(): BelongsToMany
    {
        return $this->belongsToMany(Subdivision::class, 'foreman_subdivision_user', 'foreman_user_id', 'subdivision_id')
            ->withPivot('assigned_by_user_id')
            ->withTimestamps();
    }

    /** Подразделения, за которые отвечает начальник котельной (согласование заявок). */
    public function boilerChiefSubdivisions(): BelongsToMany
    {
        return $this->belongsToMany(Subdivision::class, 'boiler_chief_subdivision_user', 'boiler_chief_user_id', 'subdivision_id')
            ->withPivot('assigned_by_user_id')
            ->withTimestamps();
    }

    public function hasRoleId(int $roleId): bool
    {
        return (int) $this->role_id === $roleId;
    }

    /**
     * @param  list<int>  $roleIds
     */
    public function hasAnyRoleId(array $roleIds): bool
    {
        return in_array((int) $this->role_id, $roleIds, true);
    }

    /** Фамилия Имя Отчество одной строкой (без лишних пробелов). */
    public function fullName(): string
    {
        $parts = array_filter([
            trim((string) $this->surname),
            trim((string) $this->name),
            trim((string) ($this->patronymic ?? '')),
        ], static fn (string $p): bool => $p !== '');

        return implode(' ', $parts);
    }
}
