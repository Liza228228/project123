<?php

namespace App\Support;

use App\Models\Subdivision;
use App\Models\SubdivisionArchive;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class SubdivisionInfrastructureDeactivation
{
    public const BOILER_CHIEF_ROLE_ID = 7;

    public const FOREMAN_ROLE_ID = 4;

    public const DETACH_ONLY_VALUE = 'detach';

    public function subdivisionHardBlockReason(Subdivision $subdivision): ?string
    {
        if (AdministrationWarehouse::isAdministrationSubdivisionId((int) $subdivision->id)) {
            return 'Подразделение «'.AdministrationWarehouse::SUBDIVISION_NAME.'» нельзя сделать недоступным.';
        }

        if ($subdivision->isArchived()) {
            return 'Подразделение «'.$subdivision->name.'» уже недоступно.';
        }

        return null;
    }

    /**
     * @return array{
     *     hard_block: ?string,
     *     subdivision_name: string,
     *     requires_staff_actions: bool,
     *     boiler_chiefs: list<array{
     *         user_id: int,
     *         label: string,
     *         has_other_subdivisions: bool,
     *         subdivision_options: list<array{id: int, name: string}>
     *     }>,
     *     foremen: list<array{
     *         user_id: int,
     *         label: string,
     *         has_other_subdivisions: bool,
     *         subdivision_options: list<array{id: int, name: string}>
     *     }>
     * }
     */
    public function subdivisionDeactivatePreview(Subdivision $subdivision): array
    {
        $hardBlock = $this->subdivisionHardBlockReason($subdivision);
        $boilerChiefs = $hardBlock === null ? $this->assignedBoilerChiefsForPreview($subdivision) : [];
        $foremen = $hardBlock === null ? $this->assignedForemenForPreview($subdivision) : [];

        return [
            'hard_block' => $hardBlock,
            'subdivision_name' => (string) $subdivision->name,
            'requires_staff_actions' => $boilerChiefs !== [] || $foremen !== [],
            'boiler_chiefs' => $boilerChiefs,
            'foremen' => $foremen,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $chiefSubdivisionAssignments
     * @param  array<int|string, mixed>  $foremanSubdivisionAssignments
     * @param  int|null  $archivedByUserId
     */
    public function deactivateSubdivision(
        Subdivision $subdivision,
        array $chiefSubdivisionAssignments = [],
        array $foremanSubdivisionAssignments = [],
        ?int $archivedByUserId = null,
    ): void {
        $hardBlock = $this->subdivisionHardBlockReason($subdivision);
        if ($hardBlock !== null) {
            throw ValidationException::withMessages([
                'subdivision' => $hardBlock,
            ]);
        }

        $chiefs = $this->assignedBoilerChiefsForPreview($subdivision);
        $foremen = $this->assignedForemenForPreview($subdivision);

        if ($chiefs !== []) {
            $this->applyStaffSubdivisionAssignments(
                $subdivision,
                $chiefSubdivisionAssignments,
                $chiefs,
                'chief_subdivisions',
                self::BOILER_CHIEF_ROLE_ID,
                'boilerChiefSubdivisions'
            );
        }

        if ($foremen !== []) {
            $this->applyStaffSubdivisionAssignments(
                $subdivision,
                $foremanSubdivisionAssignments,
                $foremen,
                'foreman_subdivisions',
                self::FOREMAN_ROLE_ID,
                'assignedSubdivisions'
            );
        }

        DB::transaction(function () use ($subdivision, $archivedByUserId): void {
            $subdivisionId = (int) $subdivision->id;

            $subdivision->siteForemen()->detach();

            if (Schema::hasTable('boiler_chief_subdivision_user')) {
                $subdivision->boilerChiefUsers()->detach();
            }

            SubdivisionArchive::query()->create([
                'subdivision_id' => $subdivisionId,
                'archived_at' => now(),
                'archived_by_user_id' => $archivedByUserId,
            ]);
        });
    }

    /**
     * @return list<array{
     *     user_id: int,
     *     label: string,
     *     has_other_subdivisions: bool,
     *     subdivision_options: list<array{id: int, name: string}>
     * }>
     */
    private function assignedBoilerChiefsForPreview(Subdivision $subdivision): array
    {
        if (! Schema::hasTable('boiler_chief_subdivision_user')) {
            return [];
        }

        return $this->assignedUsersForPreview(
            $subdivision,
            $subdivision->boilerChiefUsers()
                ->with(['boilerChiefSubdivisions' => fn ($q) => $q->orderBy('name')])
                ->orderBy('surname')
                ->orderBy('name')
                ->get(),
            self::BOILER_CHIEF_ROLE_ID,
            'boilerChiefSubdivisions'
        );
    }

    /**
     * @return list<array{
     *     user_id: int,
     *     label: string,
     *     has_other_subdivisions: bool,
     *     subdivision_options: list<array{id: int, name: string}>
     * }>
     */
    private function assignedForemenForPreview(Subdivision $subdivision): array
    {
        return $this->assignedUsersForPreview(
            $subdivision,
            $subdivision->siteForemen()
                ->with(['assignedSubdivisions' => fn ($q) => $q->orderBy('name')])
                ->orderBy('surname')
                ->orderBy('name')
                ->get(),
            self::FOREMAN_ROLE_ID,
            'assignedSubdivisions'
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $users
     * @return list<array{
     *     user_id: int,
     *     label: string,
     *     has_other_subdivisions: bool,
     *     subdivision_options: list<array{id: int, name: string}>
     * }>
     */
    private function assignedUsersForPreview(
        Subdivision $subdivision,
        $users,
        int $roleId,
        string $subdivisionsRelation
    ): array {
        $subdivisionId = (int) $subdivision->id;
        $otherSubdivisions = $this->otherActiveSubdivisionOptions($subdivisionId);
        $rows = [];

        foreach ($users as $user) {
            if (! $user->hasRoleId($roleId)) {
                continue;
            }

            $otherCount = $user->{$subdivisionsRelation}
                ->filter(fn (Subdivision $s): bool => (int) $s->id !== $subdivisionId)
                ->count();

            $rows[] = [
                'user_id' => (int) $user->id,
                'label' => trim($user->surname.' '.$user->name.' '.$user->patronymic),
                'has_other_subdivisions' => $otherCount > 0,
                'subdivision_options' => $otherSubdivisions,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function otherActiveSubdivisionOptions(int $excludeSubdivisionId): array
    {
        return Subdivision::query()
            ->active()
            ->where('id', '!=', $excludeSubdivisionId)
            ->when(
                AdministrationWarehouse::subdivisionId() !== null,
                fn (Builder $q) => $q->where('id', '!=', AdministrationWarehouse::subdivisionId())
            )
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Subdivision $s): array => [
                'id' => (int) $s->id,
                'name' => (string) $s->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{user_id: int, label: string, has_other_subdivisions: bool, subdivision_options: list<array{id: int, name: string}>}>  $expectedUsers
     * @param  array<int|string, mixed>  $rawAssignments
     */
    private function applyStaffSubdivisionAssignments(
        Subdivision $subdivision,
        array $rawAssignments,
        array $expectedUsers,
        string $errorPrefix,
        int $roleId,
        string $subdivisionsRelation
    ): void {
        $subdivisionId = (int) $subdivision->id;
        $errors = [];

        foreach ($expectedUsers as $row) {
            $userId = (int) $row['user_id'];
            $key = (string) $userId;
            $raw = $rawAssignments[$userId] ?? $rawAssignments[$key] ?? self::DETACH_ONLY_VALUE;

            if (is_array($raw)) {
                $raw = $raw[0] ?? self::DETACH_ONLY_VALUE;
            }

            $raw = is_string($raw) || is_numeric($raw) ? (string) $raw : self::DETACH_ONLY_VALUE;

            $user = User::query()->find($userId);
            if ($user === null || ! $user->hasRoleId($roleId)) {
                continue;
            }

            $currentOther = $user->{$subdivisionsRelation}()
                ->pluck('subdivisions.id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id): bool => $id !== $subdivisionId)
                ->values();

            if ($raw === self::DETACH_ONLY_VALUE || $raw === '') {
                $user->{$subdivisionsRelation}()->sync($currentOther->all());

                continue;
            }

            $newId = (int) $raw;
            if ($newId <= 0 || $newId === $subdivisionId) {
                $errors["{$errorPrefix}.{$userId}"] = 'Для «'.$row['label'].'» выберите действие.';

                continue;
            }

            if (! Subdivision::query()->active()->whereKey($newId)->exists()) {
                $errors["{$errorPrefix}.{$userId}"] = 'Выбрано недоступное или несуществующее подразделение.';

                continue;
            }

            $merged = $currentOther->push($newId)->unique()->values()->all();
            $user->{$subdivisionsRelation}()->sync($merged);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
