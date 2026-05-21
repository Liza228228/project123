<?php

namespace Database\Seeders;

use App\Support\AdministrationWarehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdministrationPrimaryWarehouseSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('warehouses') || ! Schema::hasTable('subdivisions')) {
            return;
        }

        $adminSubdivisionId = DB::table('subdivisions')
            ->where('name', AdministrationWarehouse::SUBDIVISION_NAME)
            ->value('id');

        if ($adminSubdivisionId === null) {
            return;
        }

        $adminWarehouseId = DB::table('warehouses')
            ->where('subdivision_id', $adminSubdivisionId)
            ->where('name', AdministrationWarehouse::WAREHOUSE_NAME)
            ->value('id');

        if ($adminWarehouseId === null) {
            $adminWarehouseId = DB::table('warehouses')
                ->whereRaw('LOWER(name) like ?', ['%администрац%'])
                ->orderBy('id')
                ->value('id');
        }

        if ($adminWarehouseId === null) {
            return;
        }

        DB::transaction(function () use ($adminWarehouseId, $adminSubdivisionId): void {
            DB::table('warehouses')->update(['is_primary' => false]);
            DB::table('warehouses')
                ->where('id', (int) $adminWarehouseId)
                ->update([
                    'subdivision_id' => $adminSubdivisionId,
                    'name' => AdministrationWarehouse::WAREHOUSE_NAME,
                    'is_primary' => true,
                ]);
        });
    }
}
