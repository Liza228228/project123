<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const ID_DIRECTOR = 1;
    public const ID_SUPPLY_DEPARTMENT_HEAD = 2;
    public const ID_ACCOUNTANT = 3;
    public const ID_SITE_FOREMAN = 4;
    public const ID_ADMINISTRATOR = 5;

    public const MAP = [
        self::ID_DIRECTOR => 'Директор',
        self::ID_SUPPLY_DEPARTMENT_HEAD => 'Начальник отдела снабжения',
        self::ID_ACCOUNTANT => 'Бухгалтер',
        self::ID_SITE_FOREMAN => 'Мастер участка',
        self::ID_ADMINISTRATOR => 'Администратор',
    ];

    protected $fillable = [
        'name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
