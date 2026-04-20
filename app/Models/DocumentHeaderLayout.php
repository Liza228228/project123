<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentHeaderLayout extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'schema',
        'user_assigner_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema' => 'array',
        ];
    }

    public function userAssigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_assigner_id');
    }

    public function requestLayouts(): HasMany
    {
        return $this->hasMany(RequestLayout::class, 'document_header_layout_id');
    }
}
