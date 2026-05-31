<?php

// модель
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportOption extends Model
{
    public const NAME_SELF_PICKUP = 'Самовывоз';

    public const NAME_SERVICE_VEHICLE = 'Служебная машина';

    protected $fillable = [
        'name',
        'plate',
        'label',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public static function deliveryRequiresVehiclePlate(string $methodName): bool
    {
        return trim($methodName) !== self::NAME_SELF_PICKUP;
    }

    public static function deliveryUsesServiceVehiclePlatePicker(string $methodName): bool
    {
        return trim($methodName) === self::NAME_SERVICE_VEHICLE;
    }
}
