<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApplicationItem extends Model
{
    private const MANUAL_DETAIL_KEYS = [
        'equipment_name',
        'base_name',
        'size_value',
        'measurement_type',
        'quantity_unit',
        'raw_input',
    ];

    public const CUSTOM_SUPPLY_PENDING_APPROVAL_ID = 1;
    public const CUSTOM_SUPPLY_ACCEPTED_ID = 2;
    public const CUSTOM_SUPPLY_ORDERED_ID = 3;
    public const CUSTOM_SUPPLY_IN_TRANSIT_ID = 4;
    public const CUSTOM_SUPPLY_ON_WAREHOUSE_ID = 5;

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
    public const DELIVERY_IN_TRANSIT_ID = 1;
    public const DELIVERY_DELIVERED_ID = 2;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_checked' => false,
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $manualWriteBuffer = [];

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
        'custom_equipment_supply_status_id',
        'delivery_status_id',
        'delivery_warehouse_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_checked' => 'boolean',
            'custom_equipment_supply_status_id' => 'integer',
            'delivery_status_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (ApplicationItem $item): void {
            $eid = $item->getAttributeFromArray('equipment_id');
            if ($eid !== null && $eid !== '') {
                $item->manualDetail()->delete();
                $item->manualWriteBuffer = [];

                return;
            }

            $existing = $item->manualDetail()->first();
            $base = [
                'equipment_name' => $existing?->equipment_name,
                'base_name' => $existing?->base_name ?? '—',
                'size_value' => $existing?->size_value,
                'measurement_type' => $existing?->measurement_type ?? 'piece',
                'quantity_unit' => $existing?->quantity_unit ?? 'шт',
                'raw_input' => $existing?->raw_input,
            ];
            $merged = array_merge($base, $item->manualWriteBuffer);
            $item->manualWriteBuffer = [];
            $item->manualDetail()->updateOrCreate(
                ['application_item_id' => $item->id],
                $merged
            );
            $item->unsetRelation('manualDetail');
        });
    }

    public function getAttribute($key)
    {
        if (in_array($key, self::MANUAL_DETAIL_KEYS, true)) {
            if ($this->getAttributeFromArray('equipment_id')) {
                return $this->resolveCatalogManualDetailField($key);
            }
            $this->loadMissing('manualDetail');
            $m = $this->manualDetail;

            return match ($key) {
                'equipment_name' => $m?->equipment_name,
                'base_name' => ($m && trim((string) $m->base_name) !== '') ? $m->base_name : '—',
                'size_value' => $m?->size_value,
                'measurement_type' => $m?->measurement_type ?? 'piece',
                'quantity_unit' => $m?->quantity_unit ?? 'шт',
                'raw_input' => $m?->raw_input,
                default => null,
            };
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'equipment_id') {
            parent::setAttribute($key, $value);
            if ($value !== null && $value !== '') {
                $this->manualWriteBuffer = [];
            }

            return $this;
        }

        if (in_array($key, self::MANUAL_DETAIL_KEYS, true)) {
            $eid = $this->getAttributeFromArray('equipment_id');
            if ($eid !== null && $eid !== '') {
                return $this;
            }
            $this->manualWriteBuffer[$key] = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    private function resolveCatalogManualDetailField(string $key): mixed
    {
        $this->loadMissing('equipment.measurementUnit.unitType');
        $eq = $this->equipment;
        if (! $eq) {
            return match ($key) {
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
                'base_name' => '—',
                default => null,
            };
        }

        return match ($key) {
            'equipment_name' => null,
            'base_name' => $this->catalogBaseNameLabel($eq),
            'size_value' => $eq->value,
            'measurement_type' => $eq->measurementUnit?->unitType?->code ?? 'piece',
            'quantity_unit' => $this->catalogQuantityUnitLabel($eq),
            'raw_input' => null,
            default => null,
        };
    }

    private function catalogBaseNameLabel(Equipment $eq): string
    {
        $name = trim((string) $eq->name);
        if ($name === '') {
            return '—';
        }

        $size = trim((string) ($eq->value ?? ''));
        if ($size !== '') {
            $suffix = ' '.$size;
            if (mb_substr($name, -mb_strlen($suffix)) === $suffix) {
                $base = trim((string) mb_substr($name, 0, mb_strlen($name) - mb_strlen($suffix)));
                if ($base !== '') {
                    return $base;
                }
            }
        }

        return $name;
    }

    private function catalogQuantityUnitLabel(Equipment $eq): string
    {
        $u = trim((string) ($eq->measurementUnit?->code ?? ''));
        if ($u !== '') {
            return $u;
        }
        $n = trim((string) ($eq->measurementUnit?->name ?? ''));

        return $n !== '' ? $n : 'шт';
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function manualDetail(): HasOne
    {
        return $this->hasOne(ApplicationItemManualDetail::class, 'application_item_id');
    }

    public function deliveryWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'delivery_warehouse_id');
    }

    public function getEquipmentDisplayNameAttribute(): string
    {
        $baseName = trim((string) ($this->base_name ?? ''));
        $size = trim((string) ($this->size_value ?? ''));
        if ($baseName !== '' && $baseName !== '—') {
            return trim($baseName.($size !== '' ? ' '.$size : ''));
        }

        if ($this->equipment_id && $this->equipment) {
            return $this->equipment->name;
        }

        return trim((string) ($this->equipment_name ?? '')) ?: '—';
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
        if ($this->custom_equipment_supply_status_id === null) {
            return null;
        }

        return self::customSupplyCodeFromId((int) $this->custom_equipment_supply_status_id);
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
                $w->where('custom_equipment_supply_status_id', self::CUSTOM_SUPPLY_ACCEPTED_ID)
                    ->orWhereNull('custom_equipment_supply_status_id');
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
     * Подразделение-получатель для доставки каталожного оборудования (из заявки).
     */
    public function resolvedDeliveryTargetSubdivisionId(): ?int
    {
        return $this->application?->subdivision_id ? (int) $this->application->subdivision_id : null;
    }

    public function resolvedDeliveryStatus(): ?string
    {
        if ($this->delivery_status_id === null) {
            return null;
        }

        return self::deliveryCodeFromId((int) $this->delivery_status_id);
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

    private static function customSupplyCodeFromId(int $id): ?string
    {
        return match ($id) {
            self::CUSTOM_SUPPLY_PENDING_APPROVAL_ID => self::CUSTOM_SUPPLY_PENDING_APPROVAL,
            self::CUSTOM_SUPPLY_ACCEPTED_ID => self::CUSTOM_SUPPLY_ACCEPTED,
            self::CUSTOM_SUPPLY_ORDERED_ID => self::CUSTOM_SUPPLY_ORDERED,
            self::CUSTOM_SUPPLY_IN_TRANSIT_ID => self::CUSTOM_SUPPLY_IN_TRANSIT,
            self::CUSTOM_SUPPLY_ON_WAREHOUSE_ID => self::CUSTOM_SUPPLY_ON_WAREHOUSE,
            default => null,
        };
    }

    public static function customSupplyIdFromCode(string $code): ?int
    {
        return match ($code) {
            self::CUSTOM_SUPPLY_PENDING_APPROVAL => self::CUSTOM_SUPPLY_PENDING_APPROVAL_ID,
            self::CUSTOM_SUPPLY_ACCEPTED, self::LEGACY_AWAITING_ARRIVAL => self::CUSTOM_SUPPLY_ACCEPTED_ID,
            self::CUSTOM_SUPPLY_ORDERED => self::CUSTOM_SUPPLY_ORDERED_ID,
            self::CUSTOM_SUPPLY_IN_TRANSIT => self::CUSTOM_SUPPLY_IN_TRANSIT_ID,
            self::CUSTOM_SUPPLY_ON_WAREHOUSE, self::LEGACY_ON_MAIN_WAREHOUSE => self::CUSTOM_SUPPLY_ON_WAREHOUSE_ID,
            default => null,
        };
    }

    private static function deliveryCodeFromId(int $id): ?string
    {
        return match ($id) {
            self::DELIVERY_IN_TRANSIT_ID => self::DELIVERY_IN_TRANSIT,
            self::DELIVERY_DELIVERED_ID => self::DELIVERY_DELIVERED,
            default => null,
        };
    }

    public static function deliveryIdFromCode(string $code): ?int
    {
        return match ($code) {
            self::DELIVERY_IN_TRANSIT => self::DELIVERY_IN_TRANSIT_ID,
            self::DELIVERY_DELIVERED => self::DELIVERY_DELIVERED_ID,
            default => null,
        };
    }
}
