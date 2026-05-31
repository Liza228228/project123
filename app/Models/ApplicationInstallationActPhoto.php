<?php

// модель
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationInstallationActPhoto extends Model
{
    protected $fillable = [
        'application_id',
        'path',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
