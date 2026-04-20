<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationItem extends Model
{
    public const CUSTOM_SUPPLY_PENDING_APPROVAL = 'pending_approval';

    /** Согласовано по заявке; заказ у поставщика ещё не отмечен. */
    public const CUSTOM_SUPPLY_ACCEPTED = 'accepted';

    /** Отмечено снабжением: позиция заказана у поставщика. */
    public const CUSTOM_SUPPLY_ORDERED = 'ordered';

    /** Заказано; груз от поставщика в пути (до прихода на основной склад). */
    public const CUSTOM_SUPPLY_IN_TRANSIT = 'supply_in_transit';

    /** Устаревший код в БД: после «На складе» позиция привязывается к справочнику и этот статус сбрасывается. */
    public const CUSTOM_SUPPLY_ON_WAREHOUSE = 'on_warehouse';

    /** @deprecated используйте CUSTOM_SUPPLY_ACCEPTED */
    private const LEGACY_AWAITING_ARRIVAL = 'awaiting_arrival';

    /** @deprecated используйте CUSTOM_SUPPLY_ON_WAREHOUSE */
    private const LEGACY_ON_MAIN_WAREHOUSE = 'on_main_warehouse';

    public const DELIVERY_IN_TRANSIT = 'in_transit';

    public const DELIVERY_DELIVERED = 'delivered';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_checked' => false,
    ];

    protected $fillable = [
        'application_id',
        'equipment_id',
        'equipment_name',
        'base_name',
        'size_value',
        'quantity',
        'measurement_type',
        'quantity_unit',
        'raw_input',
        'is_checked',
        'reason_not_selected',
        'custom_equipment_supply_status',
        'boiler_chief_checked',
        'reason_boiler_chief_not_selected',
        'delivery_status',
        'delivery_subdivision_id',
        'delivery_warehouse_id',
        'delivery_marked_by_user_id',
        'delivery_marked_at',
        'custom_target_subdivision_id',
        'custom_target_warehouse_id',
        'custom_foreman_in_transit',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_checked' => 'boolean',
            'boiler_chief_checked' => 'boolean',
            'delivery_marked_at' => 'datetime',
            'custom_foreman_in_transit' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function deliverySubdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class, 'delivery_subdivision_id');
    }

    public function deliveryWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'delivery_warehouse_id');
    }

    public function deliveryMarkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_marked_by_user_id');
    }

    public function customTargetSubdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class, 'custom_target_subdivision_id');
    }

    public function customTargetWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'custom_target_warehouse_id');
    }

    public function getEquipmentDisplayNameAttribute(): string
    {
        $baseName = trim((string) ($this->base_name ?? ''));
        $size = trim((string) ($this->size_value ?? ''));
        if ($baseName !== '') {
            return trim($baseName.($size !== '' ? ' '.$size : ''));
        }

        if ($this->equipment_id && $this->equipment) {
            return $this->equipment->name;
        }

        return trim($this->equipment_name ?? '') ?: '—';
    }

    public function getQuantityWithUnitAttribute(): string
    {
        $unit = trim((string) ($this->quantity_unit ?? '')) ?: 'шт';

        return ((int) $this->quantity).' '.$unit;
    }

    /**
     * Позиция без id из справочника: название введено вручную (мастер участка и т.п.).
     */
    public function usesFreeTextEquipment(): bool
    {
        return $this->equipment_id === null;
    }

    /**
     * Нормализует значение из БД (в т.ч. устаревшие коды после смены цепочки статусов).
     */
    public function normalizedCustomSupplyStatus(): ?string
    {
        $stored = $this->custom_equipment_supply_status;
        if ($stored === null || $stored === '') {
            return null;
        }
        if ($stored === self::LEGACY_ON_MAIN_WAREHOUSE) {
            return self::CUSTOM_SUPPLY_ON_WAREHOUSE;
        }
        if ($stored === self::LEGACY_AWAITING_ARRIVAL) {
            return self::CUSTOM_SUPPLY_ACCEPTED;
        }

        return $stored;
    }

    public function resolvedCustomSupplyStatus(): string
    {
        if ($this->equipment_id !== null) {
            return '';
        }

        $stored = $this->normalizedCustomSupplyStatus();
        if ($stored === self::CUSTOM_SUPPLY_ON_WAREHOUSE) {
            return self::CUSTOM_SUPPLY_ON_WAREHOUSE;
        }
        if ($stored === self::CUSTOM_SUPPLY_IN_TRANSIT) {
            return self::CUSTOM_SUPPLY_IN_TRANSIT;
        }
        if ($stored === self::CUSTOM_SUPPLY_ORDERED) {
            return self::CUSTOM_SUPPLY_ORDERED;
        }
        if ($stored === self::CUSTOM_SUPPLY_ACCEPTED) {
            return self::CUSTOM_SUPPLY_ACCEPTED;
        }
        if ($this->is_checked) {
            return self::CUSTOM_SUPPLY_ACCEPTED;
        }

        return self::CUSTOM_SUPPLY_PENDING_APPROVAL;
    }

    public function customSupplyStatusLabel(): ?string
    {
        if (! $this->usesFreeTextEquipment()) {
            return null;
        }

        return match ($this->resolvedCustomSupplyStatus()) {
            self::CUSTOM_SUPPLY_PENDING_APPROVAL => 'На согласовании',
            self::CUSTOM_SUPPLY_ACCEPTED => 'Принято по заявке',
            self::CUSTOM_SUPPLY_ORDERED => 'Заказано',
            self::CUSTOM_SUPPLY_IN_TRANSIT => 'В пути',
            self::CUSTOM_SUPPLY_ON_WAREHOUSE => 'На складе',
            default => 'Своё название',
        };
    }

    /**
     * Согласованные позиции со своим названием, по которым снабжение ещё не отметило заказ у поставщика.
     */
    public static function queryPendingCustomEquipmentOrder(): Builder
    {
        return static::query()
            ->whereHas('application', fn ($q) => $q->whereNull('archived_at'))
            ->whereNull('equipment_id')
            ->where('is_checked', true)
            ->where(function ($w) {
                $w->where('custom_equipment_supply_status', self::CUSTOM_SUPPLY_ACCEPTED)
                    ->orWhereNull('custom_equipment_supply_status');
            });
    }

    public function canMarkCustomSupplyOrdered(): bool
    {
        return $this->usesFreeTextEquipment()
            && $this->is_checked
            && $this->resolvedCustomSupplyStatus() === self::CUSTOM_SUPPLY_ACCEPTED;
    }

    public function canMarkCustomSupplyInTransit(): bool
    {
        return $this->usesFreeTextEquipment()
            && $this->is_checked
            && $this->resolvedCustomSupplyStatus() === self::CUSTOM_SUPPLY_ORDERED;
    }

    public function canMarkCustomSupplyOnWarehouse(): bool
    {
        if (! $this->usesFreeTextEquipment() || ! $this->is_checked) {
            return false;
        }

        $s = $this->resolvedCustomSupplyStatus();

        return $s === self::CUSTOM_SUPPLY_IN_TRANSIT || $s === self::CUSTOM_SUPPLY_ORDERED;
    }

    /**
     * Мастер участка: указать склад подразделения заявки, куда должна прийти поставка.
     */
    public function canSaveCustomTargetWarehouseForForeman(): bool
    {
        return $this->usesFreeTextEquipment()
            && $this->is_checked
            && $this->resolvedCustomSupplyStatus() !== self::CUSTOM_SUPPLY_PENDING_APPROVAL;
    }

    /**
     * Мастер участка: отметить, что груз в пути на выбранный склад (после того как снабжение отметило заказ).
     */
    public function canMarkCustomForemanInTransitToTarget(): bool
    {
        if (! $this->usesFreeTextEquipment() || ! $this->is_checked || $this->custom_target_warehouse_id === null) {
            return false;
        }
        if ($this->custom_foreman_in_transit) {
            return false;
        }

        $s = $this->resolvedCustomSupplyStatus();

        return $s === self::CUSTOM_SUPPLY_ORDERED || $s === self::CUSTOM_SUPPLY_IN_TRANSIT;
    }

    public function customForemanTransitSummary(): ?string
    {
        if (! $this->usesFreeTextEquipment() || ! $this->custom_foreman_in_transit) {
            return null;
        }

        $wh = $this->customTargetWarehouse;
        if ($wh) {
            return 'В пути на склад: '.$wh->name;
        }

        return 'В пути на выбранный склад';
    }

    /**
     * Подразделение, куда должна прийти поставка: из заявки или из выбора мастера (склад получения).
     */
    public function resolvedDeliveryTargetSubdivisionId(): ?int
    {
        if ($this->custom_target_subdivision_id !== null) {
            return (int) $this->custom_target_subdivision_id;
        }

        return $this->application?->subdivision_id ? (int) $this->application->subdivision_id : null;
    }

    public function resolvedDeliveryStatus(): ?string
    {
        $status = trim((string) ($this->delivery_status ?? ''));
        if ($status === self::DELIVERY_IN_TRANSIT || $status === self::DELIVERY_DELIVERED) {
            return $status;
        }

        return null;
    }

    public function deliveryStatusLabel(): ?string
    {
        return match ($this->resolvedDeliveryStatus()) {
            self::DELIVERY_IN_TRANSIT => 'В пути',
            self::DELIVERY_DELIVERED => 'Доставлено',
            default => null,
        };
    }

    public function canMarkDeliveryInTransit(): bool
    {
        return $this->is_checked
            && $this->equipment_id !== null
            && $this->resolvedDeliveryStatus() === null;
    }

    public function canMarkDeliveryDeliveredByBoilerChief(): bool
    {
        return $this->is_checked
            && $this->equipment_id !== null
            && $this->resolvedDeliveryStatus() === self::DELIVERY_IN_TRANSIT;
    }
}
