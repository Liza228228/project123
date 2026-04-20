<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Заявка по PDF-макету (таблица requests).
 */
class RequestSubmission extends Model
{
    protected $table = 'requests';

    protected $fillable = [
        'registry_number',
        'data',
        'created_by',
        'request_layout_id',
        'recipient_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function requestLayout(): BelongsTo
    {
        return $this->belongsTo(RequestLayout::class, 'request_layout_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public static function allocateRegistryNumber(): int
    {
        return (int) DB::transaction(function (): int {
            $max = static::query()->lockForUpdate()->max('registry_number');

            return (int) $max + 1;
        });
    }
}
