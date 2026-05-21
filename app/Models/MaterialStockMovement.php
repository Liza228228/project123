<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

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
        'created_by_user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $movement): void {
            if ($movement->created_by_user_id === null && Auth::id()) {
                $movement->created_by_user_id = (int) Auth::id();
            }
        });
    }

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
     * Текст комментария для интерфейса: без служебного префикса {@see self::CORR_PREFIX} и ключа идемпотентности.
     */
    public static function commentBodyForDisplay(?string $comment): ?string
    {
        if ($comment === null) {
            return null;
        }
        $comment = trim($comment);
        if ($comment === '') {
            return null;
        }
        if (! str_starts_with($comment, self::CORR_PREFIX)) {
            return $comment;
        }

        $rest = trim(substr($comment, strlen(self::CORR_PREFIX)));
        if ($rest === '') {
            return null;
        }

        if (str_contains($rest, "\n")) {
            $parts = explode("\n", $rest, 2);
            $body = trim((string) ($parts[1] ?? ''));

            return $body !== '' ? $body : null;
        }

        // Одна строка: ключ и (опционально) пояснение через пробел
        $stripped = preg_replace('#^APP:\d+:ITEM:\d+(?::[A-Za-z0-9-]+)*(?::WH:\d+)?\s+#u', '', $rest);
        $stripped = trim((string) $stripped);
        if ($stripped !== '' && $stripped !== $rest) {
            return $stripped;
        }

        if (preg_match('#^APP:\d+:ITEM:\d+(?::[A-Za-z0-9-]+)*(?::WH:\d+)?$#u', $rest)) {
            return null;
        }

        return $rest;
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function performerDisplayName(): string
    {
        $this->loadMissing('creator');

        return $this->creator?->fullName() ?? '—';
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

    /**
     * Подпись к числу в журнале (например «M» вместо «разм» для спецодежды).
     */
    public function quantityDisplaySuffix(): string
    {
        $eq = $this->equipment;
        if (! $eq) {
            return 'шт';
        }

        return $eq->quantitySuffixForMovement($this->receipt_variant);
    }
}
