<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    protected $fillable = [
        'is_primary',
        'name',
        'code',
        'address_postal_code',
        'address_region',
        'address_city',
        'address_street',
        'address_house',
        'address_block',
        'address_flat',
        'address_fias_id',
        'subdivision_id',
        'warehouse_type_id',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Строка адреса только из разобранных полей (для отображения и поиска).
     */
    protected function formattedAddress(): Attribute
    {
        return Attribute::get(fn (): string => $this->composeFormattedAddress());
    }

    private function composeFormattedAddress(): string
    {
        $top = array_filter([
            $this->address_postal_code,
            $this->address_region,
            $this->address_city,
        ]);
        $street = array_filter([
            $this->address_street,
            $this->address_house !== null && $this->address_house !== ''
                ? 'д. '.$this->address_house
                : null,
            $this->address_block ? 'корп. '.$this->address_block : null,
            $this->address_flat ? 'кв. '.$this->address_flat : null,
        ]);

        $segments = [];
        if ($top !== []) {
            $segments[] = implode(', ', $top);
        }
        if ($street !== []) {
            $segments[] = implode(', ', $street);
        }

        return implode(', ', $segments);
    }

    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }

    public function warehouseType(): BelongsTo
    {
        return $this->belongsTo(WarehouseType::class, 'warehouse_type_id');
    }
}
