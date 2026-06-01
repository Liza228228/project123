<?php

// роли и права пользователей
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS = [1, self::TECHNICAL_DIRECTOR_ROLE_ID, 2];
    public const MANAGEMENT_EDITOR_ROLE_IDS = self::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS;

    /** Директор и нач. снабжения — каталог, заказ своего оборудования, подразделения/склады, учёт. */
    public const DIRECTOR_SUPPLY_HEAD_OPERATIONS_ROLE_IDS = [1, 2];
    public const MATERIALS_CATALOG_RECEIPT_ROLE_IDS = self::DIRECTOR_SUPPLY_HEAD_OPERATIONS_ROLE_IDS;
    public const DIRECTOR_SUPPLY_HEAD_PARITY_ROLE_IDS = self::DIRECTOR_SUPPLY_HEAD_OPERATIONS_ROLE_IDS;
    public const CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS = self::DIRECTOR_SUPPLY_HEAD_OPERATIONS_ROLE_IDS;
    public const SUPPLY_PROCUREMENT_ROLE_IDS = self::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS;
    /** Просмотр справочника подразделений (без права создавать — см. SUBDIVISION_INFRASTRUCTURE_MANAGER). */
    public const SUBDIVISION_DIRECTORY_VIEW_ROLE_IDS = [
        ...self::DIRECTOR_SUPPLY_HEAD_OPERATIONS_ROLE_IDS,
        self::TECHNICAL_DIRECTOR_ROLE_ID,
        self::ACCOUNTANT_ROLE_ID,
        self::ADMINISTRATOR_ROLE_ID,
    ];
    public const SUBDIVISION_DIRECTORY_TOP_NAV_ROLE_IDS = self::SUBDIVISION_DIRECTORY_VIEW_ROLE_IDS;
    public const MATERIALS_WAREHOUSE_NAV_ROLE_IDS = [
        ...self::DIRECTOR_SUPPLY_HEAD_OPERATIONS_ROLE_IDS,
        self::TECHNICAL_DIRECTOR_ROLE_ID,
        self::FOREMAN_ROLE_ID,
        self::BOILER_CHIEF_ROLE_ID,
        self::ACCOUNTANT_ROLE_ID,
        self::ADMINISTRATOR_ROLE_ID,
    ];
    public const APPLICATION_LISTING_ROLE_IDS = [
        ...self::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS,
        self::FOREMAN_ROLE_ID,
        self::BOILER_CHIEF_ROLE_ID,
        self::ACCOUNTANT_ROLE_ID,
        self::ADMINISTRATOR_ROLE_ID,
    ];
    public const CUSTOM_EQUIPMENT_ORDER_FILTER_ROLE_IDS = [
        ...self::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS,
        self::ACCOUNTANT_ROLE_ID,
        self::ADMINISTRATOR_ROLE_ID,
    ];
    /** Полный генератор отчётов — только директор и технический директор. */
    public const REPORT_GENERATOR_ROLE_IDS = [1, self::TECHNICAL_DIRECTOR_ROLE_ID];
    public const REPORT_GENERATOR_FULL_MENU_ROLE_IDS = self::REPORT_GENERATOR_ROLE_IDS;
    public const REPORT_LAYOUT_FILL_ROLE_IDS = [
        ...self::REPORT_GENERATOR_ROLE_IDS,
        self::ACCOUNTANT_ROLE_ID,
        self::FOREMAN_ROLE_ID,
        self::BOILER_CHIEF_ROLE_ID,
        self::ADMINISTRATOR_ROLE_ID,
    ];
    public const LAYOUT_APPLICATION_REPORT_ROLE_IDS = self::REPORT_LAYOUT_FILL_ROLE_IDS;
    public const REPORT_LAYOUT_DESIGNER_ROLE_IDS = [
        ...self::REPORT_GENERATOR_ROLE_IDS,
        self::ADMINISTRATOR_ROLE_ID,
    ];
    public const REPORT_LAYOUT_CATALOG_VIEWER_ROLE_IDS = self::REPORT_LAYOUT_FILL_ROLE_IDS;
    public const ISSUE_STOCK_FROM_MAIN_ROLE_IDS = self::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS;
    public const MAIN_WAREHOUSE_STOCK_MANAGEMENT_ROLE_IDS = [
        ...self::DIRECTOR_SUPPLY_HEAD_OPERATIONS_ROLE_IDS,
        self::ADMINISTRATOR_ROLE_ID,
    ];
    public const FOREMAN_ROLE_ID = 4;
    public const SUBDIVISION_WAREHOUSE_STOCK_MANAGEMENT_ROLE_IDS = [
        self::FOREMAN_ROLE_ID,
        self::BOILER_CHIEF_ROLE_ID,
    ];
    public const ACCOUNTANT_ROLE_ID = 3;
    public const ADMINISTRATOR_ROLE_ID = 5;
    public const BOILER_CHIEF_ROLE_ID = 7;
    public const APPLICATION_CREATOR_ROLE_IDS = [
        self::FOREMAN_ROLE_ID,
        self::BOILER_CHIEF_ROLE_ID,
    ];
    public const APPLICATION_INSTALLATION_ACT_ROLE_IDS = [
        ...self::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS,
        ...self::APPLICATION_CREATOR_ROLE_IDS,
    ];
    public const TECHNICAL_DIRECTOR_ROLE_ID = 6;

    public const SUBDIVISION_ASSIGNMENT_MANAGER_ROLE_IDS = [
        ...self::DIRECTOR_SUPPLY_HEAD_OPERATIONS_ROLE_IDS,
        self::ADMINISTRATOR_ROLE_ID,
    ];
    public const SUBDIVISION_INFRASTRUCTURE_MANAGER_ROLE_IDS = [
        ...self::DIRECTOR_SUPPLY_HEAD_OPERATIONS_ROLE_IDS,
        self::ADMINISTRATOR_ROLE_ID,
    ];

    protected $fillable = [
        'surname',
        'name',
        'patronymic',
        'email',
        'password',
        'role_id',
        'is_blocked',
    ];

    protected $hidden = [
        'password',
    ];

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
    public function boilerChiefSubdivisions(): BelongsToMany
    {
        return $this->belongsToMany(Subdivision::class, 'boiler_chief_subdivision_user', 'boiler_chief_user_id', 'subdivision_id')
            ->whereDoesntHave('archive')
            ->withTimestamps();
    }
    public static function boilerChiefMaySelectForemanAsSigner(User $chief, User $foreman): bool
    {
        if (! $chief->hasRoleId(7) || ! $foreman->hasRoleId(4)) {
            return true;
        }

        $chiefIds = $chief->boilerChiefSubdivisions()->pluck('subdivisions.id')->map(fn ($id) => (int) $id)->all();
        $foremanIds = $foreman->assignedSubdivisions()->pluck('subdivisions.id')->map(fn ($id) => (int) $id)->all();

        return $chiefIds !== [] && $foremanIds !== [] && count(array_intersect($chiefIds, $foremanIds)) > 0;
    }
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
    public function hasAnyRoleId(array $roleIds): bool
    {
        return in_array((int) $this->role_id, $roleIds, true);
    }
    public function hasApplicationSupplyWorkflowRole(): bool
    {
        return $this->hasAnyRoleId(self::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS);
    }

    public function hasDirectorSupplyHeadParityRole(): bool
    {
        return $this->hasAnyRoleId(self::DIRECTOR_SUPPLY_HEAD_PARITY_ROLE_IDS);
    }

    public function canUseReportGenerator(): bool
    {
        return $this->hasAnyRoleId(self::REPORT_GENERATOR_ROLE_IDS);
    }

    public function hasDirectorSupplyHeadOperationsRole(): bool
    {
        return $this->hasAnyRoleId(self::DIRECTOR_SUPPLY_HEAD_OPERATIONS_ROLE_IDS);
    }

    public function canOrderCustomEquipment(): bool
    {
        return $this->hasAnyRoleId(self::CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS);
    }

    public function sharesApplicationWorkflowWithSupplyHead(): bool
    {
        return $this->hasApplicationSupplyWorkflowRole();
    }

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
