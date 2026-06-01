<?php

// модель
namespace App\Models;

use App\Models\Scopes\ActiveApplicationItemScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationChangeJournal extends Model
{
    /** 0 — добавление позиции */
    public const ACTION_ADDED = 0;

    /** 1 — изменение полей заявки или позиции */
    public const ACTION_UPDATED = 1;

    /** 2 — снятие позиции */
    public const ACTION_REMOVED = 2;

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
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function applicationItem(): BelongsTo
    {
        return $this->belongsTo(ApplicationItem::class)
            ->withoutGlobalScope(ActiveApplicationItemScope::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabelRu(): string
    {
        return self::actionLabelsRu()[(int) $this->action] ?? 'Изменение';
    }

    /** @return array<int, string> */
    public static function actionLabelsRu(): array
    {
        return [
            self::ACTION_ADDED => 'Добавление',
            self::ACTION_UPDATED => 'Изменение',
            self::ACTION_REMOVED => 'Снятие',
        ];
    }

    public function equipmentLineLabel(): ?string
    {
        $item = $this->applicationItem;
        if ($item === null) {
            return null;
        }

        $item->loadMissing(['equipment.measurementUnit.unitType', 'manualDetail']);

        return $item->equipment_display_name.' × '.$item->quantity_with_unit;
    }
}
