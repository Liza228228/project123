<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\Equipment;
use App\Models\Subdivision;
use App\Models\TransportOption;
use App\Models\User;
use App\Support\AdministrationWarehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class KozlovApplicationsSeeder extends Seeder
{
    public function run(): void
    {
        $kozlov = User::query()->where('email', 'Kozlov@mail.ru')->first();
        if (! $kozlov) {
            return;
        }

        $pendingId = ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING);
        $approvedId = ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED);
        $partialId = ApplicationStatus::idFor(ApplicationStatus::NAME_PARTIAL);
        $rejectedId = ApplicationStatus::idFor(ApplicationStatus::NAME_REJECTED);
        $transportQuery = TransportOption::query()->orderBy('id');
        if (Schema::hasColumn('transport_options', 'plate')) {
            $transportQuery->whereNull('plate');
        }
        $transportId = (int) ($transportQuery->value('id') ?? 0);
        $adminSubdivisionId = AdministrationWarehouse::subdivisionId();
        $allSubdivisionIds = Subdivision::query()
            ->when($adminSubdivisionId !== null, fn ($q) => $q->where('id', '!=', $adminSubdivisionId))
            ->orderBy('id')
            ->pluck('id')
            ->all();
        if ($allSubdivisionIds === []) {
            return;
        }

        $assignedSubdivisionIds = $kozlov->assignedSubdivisions()->pluck('subdivisions.id')->map(fn ($id) => (int) $id)->all();
        if ($adminSubdivisionId !== null) {
            $assignedSubdivisionIds = array_values(array_filter(
                $assignedSubdivisionIds,
                fn (int $id): bool => $id !== $adminSubdivisionId,
            ));
            if ($assignedSubdivisionIds !== []) {
                $kozlov->assignedSubdivisions()->sync($assignedSubdivisionIds);
            }
        }
        if ($assignedSubdivisionIds === []) {
            // Если у мастера ещё нет назначений, назначаем несколько подразделений для тестовых заявок.
            $assignedSubdivisionIds = array_slice($allSubdivisionIds, 0, min(3, count($allSubdivisionIds)));
            $kozlov->assignedSubdivisions()->sync($assignedSubdivisionIds);
        }

        $equipmentIds = Equipment::query()
            ->where('is_catalog', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (
            $pendingId <= 0
            || $approvedId <= 0
            || $partialId <= 0
            || $rejectedId <= 0
            || $transportId <= 0
            || count($equipmentIds) < 5
        ) {
            return;
        }

        $existingCount = Application::query()
            ->where('responsible_user_id', (int) $kozlov->id)
            ->where('user_id', (int) $kozlov->id)
            ->count();
        $targetCount = 20;
        $toCreate = max(0, $targetCount - $existingCount);
        if ($toCreate === 0) {
            return;
        }

        $statusCycle = [
            ['id' => $pendingId, 'type' => 'pending'],
            ['id' => $approvedId, 'type' => 'approved'],
            ['id' => $partialId, 'type' => 'partial'],
            ['id' => $rejectedId, 'type' => 'rejected'],
        ];
        $directorId = (int) (User::query()->where('role_id', 1)->value('id') ?? 0);

        for ($i = 0; $i < $toCreate; $i++) {
            $statusMeta = $statusCycle[$i % count($statusCycle)];
            $statusId = (int) $statusMeta['id'];
            $statusType = (string) $statusMeta['type'];

            $reason = match ($statusType) {
                'rejected' => 'Тестовый отказ (сидер Козлова).',
                default => null,
            };

            $app = Application::query()->create([
                'subdivision_id' => (int) $assignedSubdivisionIds[$i % count($assignedSubdivisionIds)],
                'responsible_user_id' => (int) $kozlov->id,
                'user_id' => (int) $kozlov->id,
                'transport_option_id' => $transportId,
                'application_status_id' => $statusId,
                'approved_by_user_id' => $directorId > 0 && $statusType !== 'pending' ? $directorId : null,
                'reason_for_refusal' => $reason,
                'desired_delivery_date' => Carbon::now()->addDays(2 + $i),
            ]);

            $itemsCount = random_int(5, 10);
            $shuffledEquipment = $equipmentIds;
            shuffle($shuffledEquipment);

            for ($line = 0; $line < $itemsCount; $line++) {
                $equipmentId = (int) $shuffledEquipment[$line % count($shuffledEquipment)];
                $isChecked = match ($statusType) {
                    'approved' => true,
                    'rejected' => false,
                    'partial' => $line < max(1, (int) floor($itemsCount / 2)),
                    default => false,
                };
                $reasonNotSelected = ! $isChecked && $statusType !== 'pending'
                    ? 'Нет на складе.'
                    : null;
                $deliveryStatusId = null;
                if ($isChecked) {
                    $deliveryStatusId = ($line % 2 === 0)
                        ? ApplicationItem::DELIVERY_DELIVERED_ID
                        : ApplicationItem::DELIVERY_IN_TRANSIT_ID;
                }

                ApplicationItem::query()->create([
                    'application_id' => (int) $app->id,
                    'equipment_id' => $equipmentId,
                    'quantity' => random_int(1, 25),
                    'is_checked' => $isChecked,
                    'reason_not_selected' => $reasonNotSelected,
                    'delivery_status_id' => $deliveryStatusId,
                ]);
            }
        }
    }
}
