<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationEditHistory;
use App\Models\ApplicationItem;
use App\Models\EquipmentType;
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

        if (! $foreman || Subdivision::query()->doesntExist() || EquipmentType::query()->doesntExist()) {
            return;
        }

        $subdivisionIds = Subdivision::query()->orderBy('id')->pluck('id')->all();
        $transportId = TransportOption::query()->orderBy('name')->value('id');
        $types = EquipmentType::query()->orderBy('id')->limit(8)->get();

        if ($types->count() < 3) {
            return;
        }

        $pickSub = static fn (int $i) => $subdivisionIds[$i % count($subdivisionIds)];

        $base = [
            'user_id' => $foreman->id,
            'responsible_user_id' => $foreman->id,
            'equipment_in_warehouse' => null,
            'transport_option_id' => $transportId,
            'source_application_id' => null,
        ];

        $app1 = Application::query()->create($base + [
            'subdivision_id' => $pickSub(0),
            'desired_delivery_date' => Carbon::now()->addDays(5),
        ]);
        ApplicationItem::query()->create([
            'application_id' => $app1->id,
            'equipment_type_id' => $types[0]->id,
            'equipment_name' => null,
            'quantity' => 4,
            'is_checked' => false,
            'reason_not_selected' => null,
        ]);
        ApplicationItem::query()->create([
            'application_id' => $app1->id,
            'equipment_type_id' => $types[1]->id,
            'equipment_name' => null,
            'quantity' => 2,
            'is_checked' => false,
            'reason_not_selected' => null,
        ]);

        $app2 = Application::query()->create($base + [
            'subdivision_id' => $pickSub(1),
            'desired_delivery_date' => Carbon::now()->addDays(12),
        ]);
        if ($director) {
            $history = ApplicationEditHistory::query()->create([
                'application_id' => $app2->id,
                'user_id' => $director->id,
                'edited_at' => now()->subHours(6),
            ]);
            $demoLines = [
                'Желаемая дата поставки: '.Carbon::now()->addDays(10)->format('d.m.Y').' → '.Carbon::now()->addDays(12)->format('d.m.Y'),
                'Добавлена позиция: «Нестандартный узел учёта (по согласованию)» × 1',
            ];
            foreach ($demoLines as $i => $body) {
                $history->lines()->create([
                    'sort_order' => $i,
                    'body' => $body,
                ]);
            }
        }
        ApplicationItem::query()->create([
            'application_id' => $app2->id,
            'equipment_type_id' => null,
            'equipment_name' => 'Нестандартный узел учёта (по согласованию)',
            'quantity' => 1,
            'is_checked' => false,
            'reason_not_selected' => null,
        ]);

        $app3 = Application::query()->create($base + [
            'subdivision_id' => $pickSub(2),
            'desired_delivery_date' => Carbon::now()->addDays(20),
            'approved_by_user_id' => $director?->id,
        ]);
        ApplicationItem::query()->create([
            'application_id' => $app3->id,
            'equipment_type_id' => $types[2]->id,
            'equipment_name' => null,
            'quantity' => 6,
            'is_checked' => true,
            'reason_not_selected' => null,
        ]);
        ApplicationItem::query()->create([
            'application_id' => $app3->id,
            'equipment_type_id' => $types[3]->id,
            'equipment_name' => null,
            'quantity' => 1,
            'is_checked' => false,
            'reason_not_selected' => 'Позиция отклонена: нет в наличии на складе поставщика.',
        ]);

        $app4 = Application::query()->create($base + [
            'subdivision_id' => $pickSub(3),
            'desired_delivery_date' => Carbon::now()->addDays(30),
            'approved_by_user_id' => $director?->id,
        ]);
        ApplicationItem::query()->create([
            'application_id' => $app4->id,
            'equipment_type_id' => $types[4]->id,
            'equipment_name' => null,
            'quantity' => 3,
            'is_checked' => true,
            'reason_not_selected' => null,
        ]);
        ApplicationItem::query()->create([
            'application_id' => $app4->id,
            'equipment_type_id' => $types[5]->id,
            'equipment_name' => null,
            'quantity' => 2,
            'is_checked' => true,
            'reason_not_selected' => null,
        ]);
    }
}
