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

class KozlovApplicationsSeeder extends Seeder
{
    public function run(): void
    {
        $kozlov = User::query()->where('email', 'Kozlov@mail.ru')->first();
        if (! $kozlov) {
            return;
        }

        $pendingId = ApplicationStatus::idFor(ApplicationStatus::CODE_PENDING);
        $transportId = (int) (TransportOption::query()->orderBy('id')->value('id') ?? 0);
        $subdivisionIds = Subdivision::query()->orderBy('id')->pluck('id')->all();
        $equipment = Equipment::query()->where('is_catalog', true)->orderBy('id')->limit(6)->get();

        if ($pendingId <= 0 || $transportId <= 0 || $subdivisionIds === [] || $equipment->count() < 2) {
            return;
        }

        for ($i = 0; $i < 3; $i++) {
            $app = Application::query()->create([
                'subdivision_id' => (int) $subdivisionIds[$i % count($subdivisionIds)],
                'responsible_user_id' => (int) $kozlov->id,
                'user_id' => (int) $kozlov->id,
                'transport_option_id' => $transportId,
                'application_status_id' => $pendingId,
                'desired_delivery_date' => Carbon::now()->addDays(3 + ($i * 4)),
            ]);

            ApplicationItem::query()->create([
                'application_id' => (int) $app->id,
                'equipment_id' => (int) $equipment[$i % $equipment->count()]->id,
                'quantity' => 5 + $i,
            ]);

            ApplicationItem::query()->create([
                'application_id' => (int) $app->id,
                'equipment_id' => (int) $equipment[($i + 1) % $equipment->count()]->id,
                'quantity' => 3 + $i,
            ]);
        }
    }
}
