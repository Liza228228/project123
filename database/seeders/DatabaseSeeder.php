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
            DepartmentSeeder::class,
            ForemanSubdivisionSeeder::class,
            WarehouseDataFrom1CsvSeeder::class,
            SubdivisionWarehouseSeeder::class,
            AdministrationPrimaryWarehouseSeeder::class,
            MeasurementUnitSeeder::class,
            EquipmentSeeder::class,
            MainWarehouseStockSeeder::class,
            TransportOptionSeeder::class,
            CustomEquipmentSupplyStatusSeeder::class,
            DeliveryStatusSeeder::class,
            ApplicationSeeder::class,
        ]);
    }
}
