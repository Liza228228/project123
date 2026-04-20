<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestLayout extends Model
{
    use SoftDeletes;

    protected $table = 'request_layout';

    protected $fillable = [
        'title',
        'schema',
        'has_header',
        'type',
        'version',
        'approver_id',
        'user_assigner_id',
        'division_assigner_id',
        'document_header_layout_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'has_header' => 'boolean',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function userAssigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_assigner_id');
    }

    public function divisionAssigner(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'division_assigner_id');
    }

    public function documentHeaderLayout(): BelongsTo
    {
        return $this->belongsTo(DocumentHeaderLayout::class, 'document_header_layout_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(RequestSubmission::class, 'request_layout_id');
    }
}
