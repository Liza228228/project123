<?php

namespace App\Support;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ForemanApplicationReassignment
{
    public const FOREMAN_ROLE_ID = 4;

    /**
     * @return Collection<int, Application>
     */
    public function applicationsRequiringReassignment(User $foreman): Collection
    {
        if (! $foreman->hasRoleId(self::FOREMAN_ROLE_ID)) {
            return collect();
        }

        $foremanId = (int) $foreman->id;

        return Application::query()
            ->notArchived()
            ->where(function (Builder $outer) use ($foremanId): void {
                $outer->where('user_id', $foremanId)
                    ->orWhere('responsible_user_id', $foremanId);
            })
            ->with(['subdivision:id,name'])
            ->orderBy('id')
            ->get();
    }

    public function requiresReassignmentBeforeBlock(User $user): bool
    {
        if (! $user->hasRoleId(self::FOREMAN_ROLE_ID)) {
            return false;
        }

        return $this->applicationsRequiringReassignment($user)->isNotEmpty();
    }

    /**
     * @return Builder<User>
     */
    public function activeForemenForSubdivisionQuery(int $subdivisionId, ?int $excludeUserId = null): Builder
    {
        $query = User::query()
            ->where('role_id', self::FOREMAN_ROLE_ID)
            ->where('is_blocked', false)
            ->whereHas('assignedSubdivisions', function (Builder $q) use ($subdivisionId): void {
                $q->where('subdivisions.id', $subdivisionId);
            });

        if ($excludeUserId !== null && $excludeUserId > 0) {
            $query->where('users.id', '!=', $excludeUserId);
        }

        return $query;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function replacementForemenOptionsForSubdivision(int $subdivisionId, int $excludeUserId): array
    {
        return $this->activeForemenForSubdivisionQuery($subdivisionId, $excludeUserId)
            ->orderBy('surname')
            ->orderBy('name')
            ->get(['id', 'surname', 'name', 'patronymic'])
            ->map(fn (User $u): array => [
                'id' => (int) $u->id,
                'label' => trim($u->surname.' '.$u->name.' '.$u->patronymic),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     id: int,
     *     subdivision_id: int,
     *     subdivision_name: string,
     *     desired_delivery_date: ?string,
     *     involvement: list<string>,
     *     foremen: list<array{id: int, label: string}>,
     *     can_reassign: bool,
     *     message: ?string
     * }>
     */
    public function blockPreviewPayload(User $foreman): array
    {
        return $this->previewPayloadForApplications(
            $this->applicationsRequiringReassignment($foreman),
            (int) $foreman->id
        );
    }

    /**
     * @param  list<int>  $newSubdivisionIds
     * @return list<int>
     */
    public function removedSubdivisionIds(User $foreman, array $newSubdivisionIds): array
    {
        $foreman->loadMissing('assignedSubdivisions:id');
        $current = $foreman->assignedSubdivisions->pluck('id')->map(fn ($id) => (int) $id);
        $new = collect($newSubdivisionIds)->map(fn ($id) => (int) $id)->unique();

        return $current->diff($new)->values()->all();
    }

    /**
     * @param  list<int>  $subdivisionIds
     * @return Collection<int, Application>
     */
    public function applicationsInSubdivisions(User $foreman, array $subdivisionIds): Collection
    {
        if (! $foreman->hasRoleId(self::FOREMAN_ROLE_ID) || $subdivisionIds === []) {
            return collect();
        }

        $foremanId = (int) $foreman->id;
        $subdivisionIds = collect($subdivisionIds)->map(fn ($id) => (int) $id)->unique()->values()->all();

        return Application::query()
            ->notArchived()
            ->whereIn('subdivision_id', $subdivisionIds)
            ->where(function (Builder $outer) use ($foremanId): void {
                $outer->where('user_id', $foremanId)
                    ->orWhere('responsible_user_id', $foremanId);
            })
            ->with(['subdivision:id,name'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<int>  $newSubdivisionIds
     */
    public function requiresReassignmentBeforeSubdivisionRemoval(User $foreman, array $newSubdivisionIds): bool
    {
        if (! $foreman->hasRoleId(self::FOREMAN_ROLE_ID)) {
            return false;
        }

        $removed = $this->removedSubdivisionIds($foreman, $newSubdivisionIds);

        return $this->applicationsInSubdivisions($foreman, $removed)->isNotEmpty();
    }

    /**
     * @param  list<int>  $newSubdivisionIds
     * @return list<array{
     *     id: int,
     *     subdivision_id: int,
     *     subdivision_name: string,
     *     desired_delivery_date: ?string,
     *     involvement: list<string>,
     *     involvement_label: string,
     *     foremen: list<array{id: int, label: string}>,
     *     can_reassign: bool,
     *     message: ?string
     * }>
     */
    public function subdivisionRemovalPreviewPayload(User $foreman, array $newSubdivisionIds): array
    {
        $removed = $this->removedSubdivisionIds($foreman, $newSubdivisionIds);

        return $this->previewPayloadForApplications(
            $this->applicationsInSubdivisions($foreman, $removed),
            (int) $foreman->id
        );
    }

    /**
     * @param  list<int>  $newSubdivisionIds
     * @param  array<int|string, mixed>  $rawReassignments
     */
    public function applySubdivisionRemovalReassignments(User $foreman, array $newSubdivisionIds, array $rawReassignments): void
    {
        if (! $foreman->hasRoleId(self::FOREMAN_ROLE_ID)) {
            return;
        }

        $removed = $this->removedSubdivisionIds($foreman, $newSubdivisionIds);
        $applications = $this->applicationsInSubdivisions($foreman, $removed);
        if ($applications->isEmpty()) {
            return;
        }

        $foremanId = (int) $foreman->id;
        $errors = [];

        foreach ($applications as $application) {
            $appId = (int) $application->id;
            $key = (string) $appId;
            $newForemanId = (int) ($rawReassignments[$appId] ?? $rawReassignments[$key] ?? 0);

            if ($newForemanId <= 0) {
                $errors["reassignments.{$appId}"] = 'Выберите мастера для заявки №'.$appId.'.';

                continue;
            }

            if ($newForemanId === $foremanId) {
                $errors["reassignments.{$appId}"] = 'Нельзя оставить заявку №'.$appId.' у снимаемого с подразделения мастера.';

                continue;
            }

            if (! $this->foremanEligibleForApplication($application, $newForemanId, $foremanId)) {
                $errors["reassignments.{$appId}"] = 'Для заявки №'.$appId.' можно выбрать только активного мастера из подразделения «'.($application->subdivision?->name ?? '—').'».';

                continue;
            }

            $this->reassignApplicationFromForeman($application, $foremanId, $newForemanId);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $stillBound = $this->applicationsInSubdivisions($foreman->fresh() ?? $foreman, $removed);
        if ($stillBound->isNotEmpty()) {
            throw ValidationException::withMessages([
                'reassignments' => 'Переназначьте все активные заявки мастера в снимаемых подразделениях перед сохранением.',
            ]);
        }
    }

    /**
     * @param  array<int|string, mixed>  $rawReassignments  application_id => foreman_user_id
     */
    public function applyBlockReassignments(User $foreman, array $rawReassignments): void
    {
        if (! $foreman->hasRoleId(self::FOREMAN_ROLE_ID)) {
            return;
        }

        $applications = $this->applicationsRequiringReassignment($foreman);
        if ($applications->isEmpty()) {
            return;
        }

        $blockedId = (int) $foreman->id;
        $errors = [];

        foreach ($applications as $application) {
            $appId = (int) $application->id;
            $key = (string) $appId;
            $newForemanId = (int) ($rawReassignments[$appId] ?? $rawReassignments[$key] ?? 0);

            if ($newForemanId <= 0) {
                $errors["reassignments.{$appId}"] = 'Выберите мастера для заявки №'.$appId.'.';

                continue;
            }

            if ($newForemanId === $blockedId) {
                $errors["reassignments.{$appId}"] = 'Нельзя оставить заявку №'.$appId.' за блокируемым мастером.';

                continue;
            }

            if (! $this->foremanEligibleForApplication($application, $newForemanId, $blockedId)) {
                $errors["reassignments.{$appId}"] = 'Для заявки №'.$appId.' можно выбрать только активного мастера из подразделения «'.($application->subdivision?->name ?? '—').'».';

                continue;
            }

            $this->reassignApplicationFromForeman($application, $blockedId, $newForemanId);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $unresolved = $this->applicationsRequiringReassignment($foreman->fresh() ?? $foreman);
        if ($unresolved->isNotEmpty()) {
            throw ValidationException::withMessages([
                'reassignments' => 'Переназначьте все активные заявки мастера перед блокировкой.',
            ]);
        }
    }

    public function reassignApplicationFromForeman(Application $application, int $fromForemanId, int $toForemanId): void
    {
        $updates = [];

        if ((int) ($application->user_id ?? 0) === $fromForemanId) {
            $updates['user_id'] = $toForemanId;
        }

        if ((int) ($application->responsible_user_id ?? 0) === $fromForemanId) {
            $updates['responsible_user_id'] = $toForemanId;
        }

        if ($updates !== []) {
            $application->update($updates);
        }
    }

    public function foremanEligibleForApplication(Application $application, int $foremanId, int $excludeForemanId = 0): bool
    {
        if ($foremanId <= 0 || ($excludeForemanId > 0 && $foremanId === $excludeForemanId)) {
            return false;
        }

        $subdivisionId = (int) $application->subdivision_id;

        return $this->activeForemenForSubdivisionQuery($subdivisionId, $excludeForemanId > 0 ? $excludeForemanId : null)
            ->where('users.id', $foremanId)
            ->exists();
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @return list<array{
     *     id: int,
     *     subdivision_id: int,
     *     subdivision_name: string,
     *     desired_delivery_date: ?string,
     *     involvement: list<string>,
     *     involvement_label: string,
     *     foremen: list<array{id: int, label: string}>,
     *     can_reassign: bool,
     *     message: ?string
     * }>
     */
    private function previewPayloadForApplications(Collection $applications, int $excludeForemanId): array
    {
        $rows = [];

        foreach ($applications as $application) {
            $subdivisionId = (int) $application->subdivision_id;
            $foremen = $this->replacementForemenOptionsForSubdivision($subdivisionId, $excludeForemanId);
            $involvement = $this->involvementLabels($application, $excludeForemanId);

            $rows[] = [
                'id' => (int) $application->id,
                'subdivision_id' => $subdivisionId,
                'subdivision_name' => (string) ($application->subdivision?->name ?? '—'),
                'desired_delivery_date' => $application->desired_delivery_date?->format('d.m.Y'),
                'involvement' => $involvement,
                'involvement_label' => implode(', ', $involvement),
                'foremen' => $foremen,
                'can_reassign' => $foremen !== [],
                'message' => $foremen === []
                    ? 'В подразделении заявки нет другого активного мастера. Назначьте другого мастера в это подразделение.'
                    : null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function involvementLabels(Application $application, int $foremanId): array
    {
        $labels = [];

        if ((int) ($application->user_id ?? 0) === $foremanId) {
            $labels[] = 'автор';
        }

        if ((int) ($application->responsible_user_id ?? 0) === $foremanId) {
            $labels[] = 'ответственный';
        }

        return $labels;
    }
}
