<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationStatus extends Model
{
    public const CODE_PENDING = 'pending';

    public const CODE_APPROVED = 'approved';

    public const CODE_REJECTED = 'rejected';

    public const CODE_PARTIAL = 'partial';

    /** Заявка закрыта: акт, фото, списания — перенос в архив выполненных. */
    public const CODE_COMPLETED = 'completed';

    protected $fillable = [
        'code',
        'name',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'application_status_id');
    }

    public static function idFor(string $code): int
    {
        $id = static::query()->where('code', $code)->value('id');

        if ($id === null) {
            throw new \RuntimeException('Unknown application status code: '.$code);
        }

        return (int) $id;
    }
}
