<?php

// модель
namespace App\Models;

use App\Models\Scopes\ActiveApplicationItemScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

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
    public const EQUIPMENT_NAME_MAX_LENGTH = 255;

    public const SIZE_VALUE_MAX_LENGTH = 120;

    public const QUANTITY_UNIT_MAX_LENGTH = 20;

    public const CUSTOM_SUPPLY_PENDING_APPROVAL_ID = 1;
    public const CUSTOM_SUPPLY_ACCEPTED_ID = 2;
    public const CUSTOM_SUPPLY_ORDERED_ID = 3;
    public const CUSTOM_SUPPLY_IN_TRANSIT_ID = 4;
    public const CUSTOM_SUPPLY_ON_WAREHOUSE_ID = 5;

    public const CUSTOM_SUPPLY_PENDING_APPROVAL = 'pending_approval';
    public const CUSTOM_SUPPLY_ACCEPTED = 'accepted';
    public const CUSTOM_SUPPLY_ORDERED = 'ordered';
    public const CUSTOM_SUPPLY_IN_TRANSIT = 'supply_in_transit';
    public const CUSTOM_SUPPLY_ON_WAREHOUSE = 'on_warehouse';
    private const LEGACY_AWAITING_ARRIVAL = 'awaiting_arrival';
    private const LEGACY_ON_MAIN_WAREHOUSE = 'on_main_warehouse';

    public const DELIVERY_IN_TRANSIT = 'in_transit';

    public const DELIVERY_DELIVERED = 'delivered';
    public const DELIVERY_IN_TRANSIT_ID = 1;
    public const DELIVERY_DELIVERED_ID = 2;
    protected $attributes = [
        'is_checked' => false,
    ];
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
        'transport_option_id',
        'expected_arrival_at',
        'removed_at',
        'removed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_checked' => 'boolean',
            'custom_equipment_supply_status_id' => 'integer',
            'delivery_status_id' => 'integer',
            'expected_arrival_at' => 'date',
            'removed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new ActiveApplicationItemScope());

        static::saved(function (ApplicationItem $item): void {
            $eid = $item->getAttributeFromArray('equipment_id');
            if ($eid !== null && $eid !== '') {
                $buffer = $item->manualWriteBuffer;
                $hasCatalogLineMeta = array_key_exists('size_value', $buffer)
                    || array_key_exists('measurement_type', $buffer)
                    || array_key_exists('quantity_unit', $buffer);
                if ($hasCatalogLineMeta) {
                    $existing = $item->manualDetail()->first();
                    $item->manualDetail()->updateOrCreate(
                        ['application_item_id' => $item->id],
                        [
                            'equipment_name' => null,
                            'base_name' => $existing?->base_name,
                            'size_value' => $buffer['size_value'] ?? $existing?->size_value,
                            'measurement_type' => $buffer['measurement_type'] ?? $existing?->measurement_type ?? 'piece',
                            'quantity_unit' => $buffer['quantity_unit'] ?? $existing?->quantity_unit ?? 'шт',
                            'raw_input' => null,
                        ]
                    );
                }
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
                if (in_array($key, ['size_value', 'measurement_type', 'quantity_unit'], true)) {
                    $this->manualWriteBuffer[$key] = $value;
                }

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

        $this->loadMissing('manualDetail');
        $md = $this->manualDetail;
        $manualSize = trim((string) ($md?->size_value ?? ''));
        $manualType = trim((string) ($md?->measurement_type ?? ''));
        $manualUnit = trim((string) ($md?->quantity_unit ?? ''));

        return match ($key) {
            'equipment_name' => null,
            'base_name' => $this->catalogBaseNameLabel($eq),
            'size_value' => $manualSize !== '' ? $manualSize : $eq->value,
            'measurement_type' => $manualType !== '' ? $manualType : ($eq->measurementUnit?->unitType?->code ?? 'piece'),
            'quantity_unit' => $manualUnit !== '' ? $manualUnit : $this->catalogQuantityUnitLabel($eq),
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

    public function transportOption(): BelongsTo
    {
        return $this->belongsTo(TransportOption::class);
    }

    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by_user_id');
    }
    public function changeJournalEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApplicationChangeJournal::class)->orderByDesc('created_at');
    }

    public function transportMethodOptionIdForDeliveryForm(): ?int
    {
        if (! Schema::hasColumn('transport_options', 'plate')) {
            return $this->transport_option_id !== null ? (int) $this->transport_option_id : null;
        }

        $this->loadMissing('transportOption');
        if ($this->transport_option_id === null || ! $this->transportOption) {
            return null;
        }

        $plate = trim((string) ($this->transportOption->plate ?? ''));
        if ($plate === '') {
            return (int) $this->transport_option_id;
        }

        return TransportOption::query()
            ->whereNull('plate')
            ->where('name', $this->transportOption->name)
            ->orderBy('id')
            ->value('id');
    }

    public function transportAndVehicleLine(): ?string
    {
        $this->loadMissing('transportOption');
        $opt = $this->transportOption;
        if (! $opt) {
            return null;
        }

        $name = trim((string) ($opt->name ?? ''));
        $plate = Schema::hasColumn('transport_options', 'plate')
            ? trim((string) ($opt->plate ?? ''))
            : '';

        if ($name === '' && $plate === '') {
            return null;
        }

        if ($name !== '' && $plate !== '') {
            return $name.' — '.$plate;
        }

        return $name !== '' ? $name : $plate;
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
        if (($this->measurement_type ?? '') === 'clothing_size') {
            $size = trim((string) ($this->size_value ?? ''));
            if ($size !== '') {
                $qty = (int) $this->quantity;

                return $qty === 1 ? $size : $qty.'×'.$size;
            }
        }

        $unit = trim((string) ($this->quantity_unit ?? '')) ?: 'шт';

        return ((int) $this->quantity).' '.$unit;
    }
    public function quantityUnitLabelForDisplay(): string
    {
        if (($this->measurement_type ?? '') === 'clothing_size') {
            $size = trim((string) ($this->size_value ?? ''));
            if ($size !== '') {
                return $size;
            }
        }

        return trim((string) ($this->quantity_unit ?? '')) ?: 'шт';
    }
    public function usesFreeTextEquipment(): bool
    {
        return $this->equipment_id === null;
    }
    public function hasArrivedAtWarehouseForReport(): bool
    {
        if (! $this->is_checked) {
            return false;
        }

        if ($this->equipment_id !== null) {
            return $this->resolvedDeliveryStatus() === self::DELIVERY_DELIVERED
                && (int) ($this->delivery_warehouse_id ?? 0) > 0;
        }

        return $this->usesFreeTextEquipment()
            && $this->resolvedCustomSupplyStatus() === self::CUSTOM_SUPPLY_ON_WAREHOUSE;
    }
    public function storedMeasurementType(): string
    {
        $this->loadMissing('manualDetail');
        $fromManual = trim((string) ($this->manualDetail?->measurement_type ?? ''));
        if ($fromManual !== '') {
            return $fromManual;
        }

        return trim((string) ($this->measurement_type ?? 'piece')) ?: 'piece';
    }
    public function storedSizeValue(): ?string
    {
        $this->loadMissing('manualDetail');
        $fromManual = trim((string) ($this->manualDetail?->size_value ?? ''));
        if ($fromManual !== '') {
            return $fromManual;
        }

        if ($this->getAttributeFromArray('equipment_id')) {
            return null;
        }

        $fromAccessor = trim((string) ($this->size_value ?? ''));

        return $fromAccessor !== '' ? $fromAccessor : null;
    }
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
            default => 'Своё оборудование',
        };
    }
    public static function queryPendingCustomEquipmentOrder(): Builder
    {
        return static::query()
            ->whereHas('application', function ($q): void {
                $q->notArchived()
                    ->whereSupplyApprovedForCustomEquipmentWorkflow();
            })
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

    public function formattedExpectedArrivalAt(): ?string
    {
        return $this->expected_arrival_at?->format('d.m.Y');
    }

    public function canMarkDeliveryInTransit(): bool
    {
        return $this->is_checked
            && $this->equipment_id !== null
            && $this->resolvedDeliveryStatus() === null;
    }
    public function isInShipmentTransitState(): bool
    {
        if ($this->usesFreeTextEquipment()) {
            return $this->resolvedCustomSupplyStatus() === self::CUSTOM_SUPPLY_IN_TRANSIT;
        }

        return $this->resolvedDeliveryStatus() === self::DELIVERY_IN_TRANSIT;
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
    public static function applicationFormValidationMessages(): array
    {
        return [
            'items.*.equipment_name.max' => 'Наименование оборудования — не более '
                .self::EQUIPMENT_NAME_MAX_LENGTH.' символов.',
            'items.*.size_value.max' => 'Размер — не более '.self::SIZE_VALUE_MAX_LENGTH.' символов.',
            'items.*.quantity_unit.max' => 'Единица измерения — не более '
                .self::QUANTITY_UNIT_MAX_LENGTH.' символов.',
        ];
    }
}
