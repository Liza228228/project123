<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationArchive extends Model
{
    protected $fillable = [
        'application_id',
        'archived_at',
        'admin_archived_at',
        'archived_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'admin_archived_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function archivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }
}
