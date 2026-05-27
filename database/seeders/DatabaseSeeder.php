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
            BoilerChiefSubdivisionSeeder::class,
            WarehouseSeeder::class,
            SubdivisionWarehouseSeeder::class,
            AdministrationPrimaryWarehouseSeeder::class,
            MeasurementUnitSeeder::class,
            EquipmentSeeder::class,
            MainWarehouseStockSeeder::class,
            TransportOptionSeeder::class,
            CustomEquipmentSupplyStatusSeeder::class,
            DeliveryStatusSeeder::class,
            ApplicationSeeder::class,
            KozlovApplicationsSeeder::class,
            KozlovCompletedApplicationsWarehouseSeeder::class,
            InstallationActDocumentHeaderLayoutSeeder::class,
            InstallationActRequestLayoutSeeder::class,
        ]);
    }
}
