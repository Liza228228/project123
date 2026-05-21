<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationStatus extends Model
{
    /** Черновик мастера участка до отправки начальнику котельной. */
    public const NAME_DRAFT = 'Черновик';

    public const NAME_PENDING = 'На согласовании';

    public const NAME_APPROVED = 'Согласована';

    public const NAME_REJECTED = 'Не согласована';

    public const NAME_PARTIAL = 'Частично согласована';

    /** Заявка закрыта: акт, фото, списания — перенос в архив выполненных. */
    public const NAME_COMPLETED = 'Выполнена';

    protected $fillable = [
        'name',
    ];

    /**
     * @var array<string, int>|null
     */
    protected static ?array $idByNameCache = null;

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'application_status_id');
    }

    public static function idFor(string $name): int
    {
        if (self::$idByNameCache === null) {
            self::$idByNameCache = static::query()->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();
        }

        if (! isset(self::$idByNameCache[$name])) {
            throw new \RuntimeException('Неизвестный статус заявки: '.$name);
        }

        return (int) self::$idByNameCache[$name];
    }

    public static function forgetIdCache(): void
    {
        self::$idByNameCache = null;
    }

    public static function idForDraft(): int
    {
        $status = static::query()->firstOrCreate(['name' => self::NAME_DRAFT]);
        self::forgetIdCache();

        return (int) $status->id;
    }
}
