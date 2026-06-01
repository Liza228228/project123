<?php

// модель
namespace App\Models;

use App\Support\RussianVehiclePlate;
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

    public static function plateMethodConflictMessage(string $plateDisplay, string $assignedMethodName, string $requestedMethodName): string
    {
        return 'Госномер '.$plateDisplay.' уже указан как «'.$assignedMethodName.'». '
            .'Для этого номера нельзя выбрать «'.$requestedMethodName.'».';
    }

    public static function plateOccupiedByInTransitMessage(string $plateDisplay, int $applicationId): string
    {
        return 'Транспорт '.$plateDisplay.' уже везёт оборудование по заявке №'.$applicationId.'. '
            .'Выберите другой номер или дождитесь приёмки текущего груза на склад — после этого машину можно назначить снова.';
    }

    /**
     * Ключ для сравнения номера между позициями (разные способы доставки — разные префиксы).
     */
    public static function deliveryPlateConsistencyKey(string $methodName, ?string $vehiclePlateRaw): ?string
    {
        $methodName = trim($methodName);
        if (! self::deliveryRequiresVehiclePlate($methodName)) {
            return null;
        }

        if (self::deliveryUsesServiceVehiclePlatePicker($methodName)) {
            $plate = mb_strtoupper(trim((string) ($vehiclePlateRaw ?? '')));

            return $plate !== '' ? 'service:'.$plate : null;
        }

        $plate = RussianVehiclePlate::normalize((string) ($vehiclePlateRaw ?? ''));

        return $plate !== '' ? 'gov:'.$plate : null;
    }

    public static function deliveryPlateDisplayLabel(string $methodName, ?string $vehiclePlateRaw): string
    {
        $methodName = trim($methodName);
        if (self::deliveryUsesServiceVehiclePlatePicker($methodName)) {
            return trim((string) ($vehiclePlateRaw ?? ''));
        }

        $formatted = RussianVehiclePlate::formatWithSpaces((string) ($vehiclePlateRaw ?? ''));

        return $formatted !== '' ? $formatted : trim((string) ($vehiclePlateRaw ?? ''));
    }
}
