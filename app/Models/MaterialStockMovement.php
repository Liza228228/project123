<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStockMovement extends Model
{
    protected $fillable = [
        'equipment_id',
        'warehouse_id',
        'type',
        'quantity',
        'unit_price',
        'happened_at',
        'document_ref',
        'counterparty',
        'comment',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'happened_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function signedQuantity(): float
    {
        $quantity = (float) $this->quantity;

        if ($this->type === 'issue') {
            return -$quantity;
        }

        return $quantity;
    }
}
