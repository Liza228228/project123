<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentHeaderLayout extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'schema',
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

    public function requestLayouts(): HasMany
    {
        return $this->hasMany(RequestLayout::class, 'document_header_layout_id');
    }
}
