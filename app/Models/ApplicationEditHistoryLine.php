<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationEditHistoryLine extends Model
{
    protected $fillable = [
        'application_edit_history_id',
        'sort_order',
        'body',
    ];

    public function history(): BelongsTo
    {
        return $this->belongsTo(ApplicationEditHistory::class, 'application_edit_history_id');
    }
}
