<?php

namespace Database\Seeders;

use App\Models\CompanyDeliveryVehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CompanyDeliveryVehicleSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('company_delivery_vehicles')) {
            return;
        }

        foreach (
            [
                ['plate' => '888', 'label' => 'Своя машина'],
                ['plate' => '777', 'label' => 'Своя машина'],
            ] as $row
        ) {
            CompanyDeliveryVehicle::query()->updateOrCreate(
                ['plate' => $row['plate']],
                ['label' => $row['label']]
            );
        }
    }
}
