<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\Equipment;
use App\Models\Subdivision;
use App\Models\TransportOption;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ApplicationSeeder extends Seeder
{
    public function run(): void
    {
        if (Application::query()->exists()) {
            return;
        }

        $foreman = User::query()->where('role_id', 4)->first();
        $director = User::query()->where('role_id', 1)->first();

        if (! $foreman || Subdivision::query()->doesntExist() || Equipment::query()->doesntExist()) {
            return;
        }

        $subdivisionIds = Subdivision::query()->orderBy('id')->pluck('id')->all();
        $transportId = TransportOption::query()->orderBy('name')->value('id');
        $types = Equipment::query()->orderBy('id')->limit(8)->get();

        if ($types->count() < 3) {
            return;
        }

        $pickSub = static fn (int $i) => $subdivisionIds[$i % count($subdivisionIds)];

        $pendingId = ApplicationStatus::idFor(ApplicationStatus::CODE_PENDING);
        $approvedId = ApplicationStatus::idFor(ApplicationStatus::CODE_APPROVED);
        $partialId = ApplicationStatus::query()->where('code', ApplicationStatus::CODE_PARTIAL)->value('id');
        $partialId = $partialId !== null ? (int) $partialId : ApplicationStatus::idFor(ApplicationStatus::CODE_REJECTED);

        $base = [
            'user_id' => $foreman->id,
            'responsible_user_id' => $foreman->id,
            'transport_option_id' => $transportId,
            'source_application_id' => null,
            'application_status_id' => $pendingId,
        ];

        $app1 = Application::query()->create($base + [
            'subdivision_id' => $pickSub(0),
            'desired_delivery_date' => Carbon::now()->addDays(5),
        ]);
        ApplicationItem::query()->create([
            'application_id' => $app1->id,
            'equipment_id' => $types[0]->id,
            'equipment_name' => null,
            'quantity' => 12,
        ]);
        ApplicationItem::query()->create([
            'application_id' => $app1->id,
            'equipment_id' => $types[1]->id,
            'equipment_name' => null,
            'quantity' => 8,
        ]);

        $app2 = Application::query()->create($base + [
            'subdivision_id' => $pickSub(1),
            'desired_delivery_date' => Carbon::now()->addDays(12),
        ]);
        ApplicationItem::query()->create([
            'application_id' => $app2->id,
            'equipment_id' => null,
            'equipment_name' => 'Нестандартный узел учёта (по согласованию)',
            'quantity' => 3,
            'custom_equipment_supply_status_id' => ApplicationItem::CUSTOM_SUPPLY_PENDING_APPROVAL_ID,
        ]);

        $app3 = Application::query()->create($base + [
            'subdivision_id' => $pickSub(2),
            'desired_delivery_date' => Carbon::now()->addDays(20),
            'approved_by_user_id' => $director?->id,
            'application_status_id' => $partialId,
            'approval_rejection_reason' => null,
        ]);
        ApplicationItem::query()->create([
            'application_id' => $app3->id,
            'equipment_id' => $types[2]->id,
            'equipment_name' => null,
            'quantity' => 15,
            'is_checked' => true,
            'reason_not_selected' => null,
        ]);
        ApplicationItem::query()->create([
            'application_id' => $app3->id,
            'equipment_id' => $types[3]->id,
            'equipment_name' => null,
            'quantity' => 5,
            'is_checked' => false,
            'reason_not_selected' => 'Нет в наличии на складе поставщика.',
        ]);

        $app4 = Application::query()->create($base + [
            'subdivision_id' => $pickSub(3),
            'desired_delivery_date' => Carbon::now()->addDays(30),
            'approved_by_user_id' => $director?->id,
            'application_status_id' => $approvedId,
            'approval_rejection_reason' => null,
        ]);
        ApplicationItem::query()->create([
            'application_id' => $app4->id,
            'equipment_id' => $types[4]->id,
            'equipment_name' => null,
            'quantity' => 10,
            'is_checked' => true,
            'reason_not_selected' => null,
        ]);
        ApplicationItem::query()->create([
            'application_id' => $app4->id,
            'equipment_id' => $types[5]->id,
            'equipment_name' => null,
            'quantity' => 7,
            'is_checked' => true,
            'reason_not_selected' => null,
        ]);
    }
}
