<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /** роли  */
    public const ROLE_DIRECTOR = 'Директор';

    public const ROLE_SUPPLY_DEPARTMENT_HEAD = 'Начальник отдела снабжения';

    public const ROLE_ACCOUNTANT = 'Бухгалтер';

    public const ROLE_SITE_FOREMAN = 'Мастер участка';

    public const ROLE_ADMINISTRATOR = 'Администратор';

    public const ROLES = [
        self::ROLE_DIRECTOR,
        self::ROLE_SUPPLY_DEPARTMENT_HEAD,
        self::ROLE_ACCOUNTANT,
        self::ROLE_SITE_FOREMAN,
        self::ROLE_ADMINISTRATOR,
    ];

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
        'role',
        'is_blocked',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
