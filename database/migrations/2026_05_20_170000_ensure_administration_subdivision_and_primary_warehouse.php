<?php

use App\Support\AdministrationWarehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subdivisions') || ! Schema::hasTable('warehouses')) {
            return;
        }

        $now = now();

        $adminSubdivisionId = DB::table('subdivisions')
            ->where('name', AdministrationWarehouse::SUBDIVISION_NAME)
            ->value('id');

        if ($adminSubdivisionId === null) {
            $adminSubdivisionId = DB::table('subdivisions')->insertGetId([
                'name' => AdministrationWarehouse::SUBDIVISION_NAME,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $officeWarehouse = DB::table('warehouses')
            ->where(function ($q): void {
                $q->where('name', AdministrationWarehouse::WAREHOUSE_NAME)
                    ->orWhereRaw('LOWER(name) like ?', ['%администрац%офис%'])
                    ->orWhere(function ($q2): void {
                        $q2->where('is_primary', true)
                            ->whereRaw('LOWER(name) like ?', ['%администрац%']);
                    });
            })
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        if ($officeWarehouse === null) {
            $defaultTypeId = DB::table('warehouse_types')
                ->where('name', 'Оптовый склад')
                ->value('id');

            DB::table('warehouses')->insert([
                'is_primary' => true,
                'name' => AdministrationWarehouse::WAREHOUSE_NAME,
                'subdivision_id' => $adminSubdivisionId,
                'warehouse_type_id' => $defaultTypeId,
                'comment' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('warehouses')
                ->where('id', (int) $officeWarehouse->id)
                ->update([
                    'name' => AdministrationWarehouse::WAREHOUSE_NAME,
                    'subdivision_id' => $adminSubdivisionId,
                    'is_primary' => true,
                    'updated_at' => $now,
                ]);
        }

        DB::table('warehouses')->update(['is_primary' => false]);

        $primaryId = DB::table('warehouses')
            ->where('subdivision_id', $adminSubdivisionId)
            ->where('name', AdministrationWarehouse::WAREHOUSE_NAME)
            ->value('id');

        if ($primaryId !== null) {
            DB::table('warehouses')
                ->where('id', (int) $primaryId)
                ->update(['is_primary' => true]);
        }
    }

    public function down(): void
    {
        // Структурный перенос данных; откат не выполняется.
    }
};
