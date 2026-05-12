<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStockMovement extends Model
{
    public const CORR_PREFIX = '__CORR__:';

    protected $fillable = [
        'equipment_id',
        'warehouse_id',
        'material_stock_movement_type_id',
        'quantity',
        'receipt_variant',
        'unit_price',
        'counterparty',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
        ];
    }

    public static function packCommentWithCorrelation(string $correlationKey, string $body = ''): string
    {
        $key = trim($correlationKey);
        $prefix = self::CORR_PREFIX.$key;
        $body = trim($body);

        return $body === '' ? $prefix : $prefix."\n".$body;
    }

    /**
     * Совпадение с ключом идемпотентности в comment (точное, с текстом после перевода строки или с суффиксом через «:», например …:INSTALL).
     */
    public function scopeWhereCorrelationKey(Builder $query, string $correlationKey): void
    {
        $p = self::CORR_PREFIX.trim($correlationKey);
        $query->where(function (Builder $w) use ($p) {
            $w->where('comment', $p)
                ->orWhere('comment', 'like', $p."\n%")
                ->orWhere('comment', 'like', $p.':%');
        });
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function movementType(): BelongsTo
    {
        return $this->belongsTo(MaterialStockMovementType::class, 'material_stock_movement_type_id');
    }

    public function signedQuantity(): float
    {
        $quantity = (float) $this->quantity;
        $name = $this->relationLoaded('movementType')
            ? $this->movementType?->name
            : MaterialStockMovementType::query()->whereKey($this->material_stock_movement_type_id)->value('name');

        if ($name === MaterialStockMovementType::NAME_ISSUE) {
            return -$quantity;
        }

        return $quantity;
    }
}
