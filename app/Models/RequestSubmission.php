<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Заявка по PDF-макету (таблица requests).
 */
class RequestSubmission extends Model
{
    protected $table = 'requests';

    protected $fillable = [
        'data',
        'created_by',
        'layout_structure_id',
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
        return $this->belongsTo(RequestLayout::class, 'layout_structure_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
