<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    public const NAME_MAX_LENGTH = 150;

    protected $table = 'equipment';

    protected $fillable = [
        'name',
        'value',
        'measurement_unit_id',
        'unit_type_id',
        'is_catalog',
    ];

    protected function casts(): array
    {
        return [
            'is_catalog' => 'boolean',
        ];
    }

    public function applicationItems(): HasMany
    {
        return $this->hasMany(ApplicationItem::class, 'equipment_id');
    }

    public function measurementUnit(): BelongsTo
    {
        return $this->belongsTo(MeasurementUnit::class, 'measurement_unit_id');
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
    }

    public function getDisplayNameAttribute(): string
    {
        $name = trim((string) $this->name);
        $value = trim((string) ($this->value ?? ''));

        return $value !== '' ? trim($name.' '.$value) : $name;
    }

    /**
     * Подпись к количеству в остатках: для размера одежды — маркировка (M, L), иначе код единицы измерения.
     */
    public function stockQuantityUnitLabel(): string
    {
        $this->loadMissing('measurementUnit.unitType');
        if (($this->measurementUnit?->unitType?->code ?? '') === 'clothing_size') {
            $size = trim((string) ($this->value ?? ''));
            if ($size !== '') {
                return $size;
            }
        }

        return trim((string) ($this->measurementUnit?->code ?? '')) ?: 'шт';
    }

    /**
     * Суффикс количества в журнале движений: размер из строки прихода или из карточки оборудования.
     */
    public function quantitySuffixForMovement(?string $receiptVariant): string
    {
        $this->loadMissing('measurementUnit.unitType');
        if (($this->measurementUnit?->unitType?->code ?? '') === 'clothing_size') {
            $fromReceipt = trim((string) ($receiptVariant ?? ''));
            if ($fromReceipt !== '') {
                return $fromReceipt;
            }
            $fromCard = trim((string) ($this->value ?? ''));
            if ($fromCard !== '') {
                return $fromCard;
            }
        }

        return trim((string) ($this->measurementUnit?->code ?? '')) ?: 'шт';
    }

    public static function catalogEntryExists(string $name, string $unitTypeCode): bool
    {
        $normalizedName = mb_strtolower(trim($name));
        $unitTypeCode = trim($unitTypeCode);

        if ($normalizedName === '' || $unitTypeCode === '') {
            return false;
        }

        return static::query()
            ->where('is_catalog', true)
            ->whereHas('measurementUnit.unitType', fn ($query) => $query->where('code', $unitTypeCode))
            ->pluck('name')
            ->contains(fn (string $storedName) => mb_strtolower(trim($storedName)) === $normalizedName);
    }
}
