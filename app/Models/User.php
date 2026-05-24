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

    /**
     * Директор, технический директор и начальник отдела снабжения — одинаковая работа с заявками:
     * список и создание, согласование, КП, отметки поставки, списание со склада «Администрация».
     *
     * @var list<int>
     */
    public const APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS = [1, self::TECHNICAL_DIRECTOR_ROLE_ID, 2];

    /** @var list<int> */
    public const MANAGEMENT_EDITOR_ROLE_IDS = self::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS;

    /**
     * Справочник оборудования и приход/операции на складе (маршрут «Учёт оборудования», POST каталога и движений).
     * Бухгалтер (роль 3) по-прежнему может открыть /materials для просмотра без этих прав — см. middleware {@see \App\Http\Middleware\EnsureUserIsSupplyHead}.
     * Технический директор — только остатки и журнал, без учёта.
     *
     * @var list<int>
     */
    public const MATERIALS_CATALOG_RECEIPT_ROLE_IDS = [1, 2];

    /**
     * Директор, технический директор и начальник снабжения — отметки поставки и прочие операции снабжения
     * (без раздела «Учёт оборудования» у технического директора).
     *
     * @var list<int>
     */
    public const SUPPLY_PROCUREMENT_ROLE_IDS = self::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS;

    /**
     * Директор и начальник отдела снабжения — полный паритет (включая учёт оборудования).
     *
     * @var list<int>
     */
    public const DIRECTOR_SUPPLY_HEAD_PARITY_ROLE_IDS = self::MATERIALS_CATALOG_RECEIPT_ROLE_IDS;

    /** Прямая ссылка «Подразделения» в верхнем меню (каталог подразделений и складов). */
    public const SUBDIVISION_DIRECTORY_TOP_NAV_ROLE_IDS = [1, 2, 6, 3, 5];

    /** Раздел «Оборудование к заказу» и формы заказа своего оборудования (без технического директора). */
    public const CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS = self::MATERIALS_CATALOG_RECEIPT_ROLE_IDS;

    /**
     * Заполнение отчётов по макету, каталог макетов для заполнения, «Отчеты по макетам» — все роли.
     *
     * @var list<int>
     */
    public const REPORT_LAYOUT_FILL_ROLE_IDS = [1, 2, 3, 4, 5, 6, 7];

    /** @var list<int> */
    public const LAYOUT_APPLICATION_REPORT_ROLE_IDS = self::REPORT_LAYOUT_FILL_ROLE_IDS;

    /**
     * Полное меню «Генератор отчётов» (шапки, PDF-макеты, отчёты по макетам) — у директора и ТД идентично.
     *
     * @var list<int>
     */
    public const REPORT_GENERATOR_FULL_MENU_ROLE_IDS = [1, self::TECHNICAL_DIRECTOR_ROLE_ID];

    /** Директор, технический директор и администратор — макеты шапок и конструктор макетов отчётов (PDF). */
    public const REPORT_LAYOUT_DESIGNER_ROLE_IDS = [
        ...self::REPORT_GENERATOR_FULL_MENU_ROLE_IDS,
        self::ADMINISTRATOR_ROLE_ID,
    ];

    /** @var list<int> */
    public const REPORT_LAYOUT_CATALOG_VIEWER_ROLE_IDS = self::REPORT_LAYOUT_FILL_ROLE_IDS;

    /** Списание со склада «Администрация» по согласованным позициям из справочника. */
    public const ISSUE_STOCK_FROM_MAIN_ROLE_IDS = self::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS;

    /** Бухгалтер — просмотр всех заявок и актов установки. */
    public const ACCOUNTANT_ROLE_ID = 3;

    /** Администратор — пользователи и блокировки. */
    public const ADMINISTRATOR_ROLE_ID = 5;

    /** Начальник котельной. */
    public const BOILER_CHIEF_ROLE_ID = 7;

    /**
     * Роли, которые создают заявки (и могут принудительно переносить их в архив).
     *
     * @var list<int>
     */
    public const APPLICATION_CREATOR_ROLE_IDS = [
        ...self::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS,
        4,
        self::BOILER_CHIEF_ROLE_ID,
    ];

    /** Технический директор. */
    public const TECHNICAL_DIRECTOR_ROLE_ID = 6;

    /**
     * Назначение мастеров участка и начальников котельных по подразделениям
     * (директор, технический директор, начальник отдела снабжения, администратор).
     */
    public const SUBDIVISION_ASSIGNMENT_MANAGER_ROLE_IDS = [1, self::TECHNICAL_DIRECTOR_ROLE_ID, 2, 5];

    /**
     * Создание подразделений и складов (без технического директора — только просмотр каталога).
     *
     * @var list<int>
     */
    public const SUBDIVISION_INFRASTRUCTURE_MANAGER_ROLE_IDS = [1, 2, 5];

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
            ->whereDoesntHave('archive')
            ->withTimestamps();
    }

    /** Подразделения, за которые отвечает начальник котельной (согласование заявок). */
    public function boilerChiefSubdivisions(): BelongsToMany
    {
        return $this->belongsToMany(Subdivision::class, 'boiler_chief_subdivision_user', 'boiler_chief_user_id', 'subdivision_id')
            ->whereDoesntHave('archive')
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
     * Пользователи для выбора подписи в отчёте по макету (роль и подразделения).
     *
     * @param  \Illuminate\Support\Collection<int, self>|\Illuminate\Database\Eloquent\Collection<int, self>  $users
     * @return list<array{id: int, label: string, role_id: int, role_name: string, subdivision_ids: list<int>}>
     */
    public static function layoutReportSignerOptions($users): array
    {
        return $users
            ->map(function (self $user): array {
                $roleId = (int) ($user->role_id ?? 0);
                $subdivisionIds = match ($roleId) {
                    4 => $user->assignedSubdivisions
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all(),
                    self::BOILER_CHIEF_ROLE_ID => $user->boilerChiefSubdivisions
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all(),
                    default => [],
                };

                return [
                    'id' => (int) $user->id,
                    'label' => $user->fullName(),
                    'role_id' => $roleId,
                    'role_name' => (string) ($user->role?->name ?? ''),
                    'subdivision_ids' => $subdivisionIds,
                ];
            })
            ->values()
            ->all();
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

    /** Директор, ТД или начальник снабжения — полный цикл работы с заявками. */
    public function hasApplicationSupplyWorkflowRole(): bool
    {
        return $this->hasAnyRoleId(self::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS);
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
