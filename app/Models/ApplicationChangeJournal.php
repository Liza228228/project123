<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationChangeJournal extends Model
{
    public const ACTION_ADDED = 'added';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_REMOVED = 'removed';

    public const FIELD_SUBDIVISION = 'subdivision_id';

    public const FIELD_DELIVERY_DATE = 'desired_delivery_date';

    public const FIELD_ITEM_ADDED = 'item_added';

    public const FIELD_ITEM_UPDATED = 'item_updated';

    public const FIELD_ITEM_REMOVED = 'item_removed';

    public const UPDATED_AT = null;

    protected $table = 'application_change_journal';

    protected $fillable = [
        'application_id',
        'application_item_id',
        'user_id',
        'action',
        'field_key',
        'field_label',
        'old_value',
        'new_value',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function applicationItem(): BelongsTo
    {
        return $this->belongsTo(ApplicationItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabelRu(): string
    {
        return match ($this->action) {
            self::ACTION_ADDED => 'Добавление',
            self::ACTION_REMOVED => 'Снятие',
            default => 'Изменение',
        };
    }
}
