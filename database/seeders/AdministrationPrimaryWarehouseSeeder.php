<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdministrationPrimaryWarehouseSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('warehouses')) {
            return;
        }

        $adminWarehouseId = DB::table('warehouses')
            ->whereRaw('LOWER(name) like ?', ['%администрац%'])
            ->orderBy('id')
            ->value('id');

        if ($adminWarehouseId === null) {
            return;
        }

        DB::transaction(function () use ($adminWarehouseId): void {
            DB::table('warehouses')->update(['is_primary' => false]);
            DB::table('warehouses')
                ->where('id', (int) $adminWarehouseId)
                ->update(['is_primary' => true]);
        });
    }
}
