<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialStockMovementType extends Model
{
    public const NAME_RECEIPT = 'Приход';

    public const NAME_ISSUE = 'Списание';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    /**
     * @var array<string, int>|null
     */
    protected static ?array $idByNameCache = null;

    public static function idFor(string $name): int
    {
        if (self::$idByNameCache === null) {
            self::$idByNameCache = static::query()->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();
        }

        if (! isset(self::$idByNameCache[$name])) {
            throw new \InvalidArgumentException('Неизвестный тип движения: '.$name);
        }

        return (int) self::$idByNameCache[$name];
    }

    public static function forgetIdCache(): void
    {
        self::$idByNameCache = null;
    }

    public function movements(): HasMany
    {
        return $this->hasMany(MaterialStockMovement::class, 'material_stock_movement_type_id');
    }
}
