<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\EquipmentType;
use App\Models\Role;
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

        $foreman = User::where('role_id', Role::ID_SITE_FOREMAN)->first();
        $director = User::where('role_id', Role::ID_DIRECTOR)->first();

        if (! $foreman || Subdivision::query()->doesntExist() || EquipmentType::query()->doesntExist()) {
            return;
        }

        $subdivisionIds = Subdivision::query()->orderBy('id')->pluck('id')->all();
        $transportId = TransportOption::query()->orderBy('sort_order')->value('id');
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
            'approved_at' => null,
            'director_last_edited_at' => null,
            'director_last_edited_by' => null,
            'director_last_edit_detail' => null,
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
            'approved_at' => null,
            'director_last_edited_at' => $director ? now()->subHours(6) : null,
            'director_last_edited_by' => $director?->id,
            'director_last_edit_detail' => $director ? implode("\n", [
                'Желаемая дата поставки: '.Carbon::now()->addDays(10)->format('d.m.Y').' → '.Carbon::now()->addDays(12)->format('d.m.Y'),
                'Добавлена позиция: «Нестандартный узел учёта (по согласованию)» × 1',
            ]) : null,
        ]);
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
            'approved_at' => now()->subDay(),
            'director_last_edited_at' => null,
            'director_last_edited_by' => null,
            'director_last_edit_detail' => null,
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
            'approved_at' => now()->subDays(2),
            'director_last_edited_at' => null,
            'director_last_edited_by' => null,
            'director_last_edit_detail' => null,
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
