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
            EquipmentTypeSeeder::class,
            TransportOptionSeeder::class,
            ApplicationSeeder::class,
        ]);
    }
}
