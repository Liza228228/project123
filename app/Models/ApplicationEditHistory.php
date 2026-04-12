<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationEditHistory extends Model
{
    protected $fillable = [
        'application_id',
        'user_id',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ApplicationEditHistoryLine::class)->orderBy('sort_order');
    }

    /**
     * @return list<string>
     */
    public function lineBodies(): array
    {
        return $this->lines()->pluck('body')->all();
    }
}
