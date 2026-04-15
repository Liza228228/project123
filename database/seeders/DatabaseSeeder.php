<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            SubdivisionSeeder::class,
            WarehouseDataFrom1CsvSeeder::class,
            MeasurementUnitSeeder::class,
            EquipmentSeeder::class,
            TransportOptionSeeder::class,
            ApplicationSeeder::class,
            ApplicationReportLayoutSeeder::class,
        ]);
    }
}
