<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /** Директор, технический директор, начальник снабжения — согласование заявок, списания, материалы и т.д. */
    public const MANAGEMENT_EDITOR_ROLE_IDS = [1, 6, 2];

    /**
     * Справочник оборудования и приход/операции на складе (маршрут «Учёт оборудования», POST каталога и движений).
     * Бухгалтер (роль 3) по-прежнему может открыть /materials для просмотра без этих прав — см. middleware {@see \App\Http\Middleware\EnsureUserIsSupplyHead}.
     *
     * @var list<int>
     */
    public const MATERIALS_CATALOG_RECEIPT_ROLE_IDS = [1, 2];

    /**
     * Директор и начальник отдела снабжения — одинаковый набор действий по заявкам и складу
     * (заказ своего оборудования, пункты меню и т.п.).
     *
     * @var list<int>
     */
    public const DIRECTOR_SUPPLY_HEAD_PARITY_ROLE_IDS = [1, 2];

    /** Прямая ссылка «Подразделения» в верхнем меню (каталог подразделений и складов). */
    public const SUBDIVISION_DIRECTORY_TOP_NAV_ROLE_IDS = [1, 2, 6, 3, 5];

    /** Директор и начальник снабжения — заказ нестандартного («своего») оборудования и приход на основной склад по нему. */
    public const CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS = self::DIRECTOR_SUPPLY_HEAD_PARITY_ROLE_IDS;

    /**
     * Заполнение отчётов по макету, каталог макетов для заполнения, «Отчеты по макетам» — все роли.
     *
     * @var list<int>
     */
    public const REPORT_LAYOUT_FILL_ROLE_IDS = [1, 2, 3, 4, 5, 6, 7];

    /** @var list<int> */
    public const LAYOUT_APPLICATION_REPORT_ROLE_IDS = self::REPORT_LAYOUT_FILL_ROLE_IDS;

    /** Директор, технический директор и администратор — макеты шапок и конструктор макетов отчётов (PDF). */
    public const REPORT_LAYOUT_DESIGNER_ROLE_IDS = [1, 6, self::ADMINISTRATOR_ROLE_ID];

    /** @var list<int> */
    public const REPORT_LAYOUT_CATALOG_VIEWER_ROLE_IDS = self::REPORT_LAYOUT_FILL_ROLE_IDS;

    /** Списание со склада «Администрация» по согласованным позициям из справочника. */
    public const ISSUE_STOCK_FROM_MAIN_ROLE_IDS = [1, 2, 6];

    /** Бухгалтер — просмотр всех заявок и актов установки. */
    public const ACCOUNTANT_ROLE_ID = 3;

    /** Администратор — пользователи и блокировки. */
    public const ADMINISTRATOR_ROLE_ID = 5;

    /**
     * Назначение мастеров участка и начальников котельных по подразделениям
     * (директор, технический директор, начальник отдела снабжения, администратор).
     */
    public const SUBDIVISION_ASSIGNMENT_MANAGER_ROLE_IDS = [1, 6, 2, 5];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'surname',
        'name',
        'patronymic',
        'email',
        'password',
        'role_id',
        'is_blocked',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_blocked' => 'boolean',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function layoutApplications(): HasMany
    {
        return $this->hasMany(RequestSubmission::class, 'created_by');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function assignedSubdivisions(): BelongsToMany
    {
        return $this->belongsToMany(Subdivision::class, 'foreman_subdivision_user', 'foreman_user_id', 'subdivision_id')
            ->withTimestamps();
    }

    /** Подразделения, за которые отвечает начальник котельной (согласование заявок). */
    public function boilerChiefSubdivisions(): BelongsToMany
    {
        return $this->belongsToMany(Subdivision::class, 'boiler_chief_subdivision_user', 'boiler_chief_user_id', 'subdivision_id')
            ->withTimestamps();
    }

    /**
     * Начальник котельной может выбрать этого мастера участка как подписанта (пересечение подразделений).
     */
    public static function boilerChiefMaySelectForemanAsSigner(User $chief, User $foreman): bool
    {
        if (! $chief->hasRoleId(7) || ! $foreman->hasRoleId(4)) {
            return true;
        }

        $chiefIds = $chief->boilerChiefSubdivisions()->pluck('subdivisions.id')->map(fn ($id) => (int) $id)->all();
        $foremanIds = $foreman->assignedSubdivisions()->pluck('subdivisions.id')->map(fn ($id) => (int) $id)->all();

        return $chiefIds !== [] && $foremanIds !== [] && count(array_intersect($chiefIds, $foremanIds)) > 0;
    }

    /**
     * Для формы «Отчёт по макету»: фильтр мастеров по подразделениям начальника котельной.
     *
     * @return array{isBoilerChief: bool, foremanRoleId: int, chiefSubdivisionIds: list<int>}
     */
    public static function layoutReportViewerContext(?User $user): array
    {
        if (! $user?->hasRoleId(7)) {
            return ['isBoilerChief' => false, 'foremanRoleId' => 4, 'chiefSubdivisionIds' => []];
        }

        return [
            'isBoilerChief' => true,
            'foremanRoleId' => 4,
            'chiefSubdivisionIds' => $user->boilerChiefSubdivisions()
                ->pluck('subdivisions.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
        ];
    }

    public function hasRoleId(int $roleId): bool
    {
        return (int) $this->role_id === $roleId;
    }

    /**
     * @param  list<int>  $roleIds
     */
    public function hasAnyRoleId(array $roleIds): bool
    {
        return in_array((int) $this->role_id, $roleIds, true);
    }

    /** Фамилия Имя Отчество одной строкой (без лишних пробелов). */
    public function fullName(): string
    {
        $parts = array_filter([
            trim((string) $this->surname),
            trim((string) $this->name),
            trim((string) ($this->patronymic ?? '')),
        ], static fn (string $p): bool => $p !== '');

        return implode(' ', $parts);
    }
}
