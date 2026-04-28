<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_items')) {
            return;
        }

        if (! Schema::hasTable('custom_equipment_supply_statuses') || ! Schema::hasTable('delivery_statuses')) {
            return;
        }

        if (Schema::hasColumn('application_items', 'custom_equipment_supply_status_id')) {
            $validCustomIds = DB::table('custom_equipment_supply_statuses')->pluck('id');
            DB::table('application_items')
                ->whereNotNull('custom_equipment_supply_status_id')
                ->whereNotIn('custom_equipment_supply_status_id', $validCustomIds)
                ->update(['custom_equipment_supply_status_id' => null]);
        }

        if (Schema::hasColumn('application_items', 'delivery_status_id')) {
            $validDeliveryIds = DB::table('delivery_statuses')->pluck('id');
            DB::table('application_items')
                ->whereNotNull('delivery_status_id')
                ->whereNotIn('delivery_status_id', $validDeliveryIds)
                ->update(['delivery_status_id' => null]);
        }

        Schema::table('application_items', function (Blueprint $table) {
            if (
                Schema::hasColumn('application_items', 'custom_equipment_supply_status_id')
                && ! $this->foreignKeyExists('application_items', 'application_items_custom_supply_status_fk')
            ) {
                $table->foreign('custom_equipment_supply_status_id', 'application_items_custom_supply_status_fk')
                    ->references('id')
                    ->on('custom_equipment_supply_statuses')
                    ->nullOnDelete();
            }

            if (
                Schema::hasColumn('application_items', 'delivery_status_id')
                && ! $this->foreignKeyExists('application_items', 'application_items_delivery_status_fk')
            ) {
                $table->foreign('delivery_status_id', 'application_items_delivery_status_fk')
                    ->references('id')
                    ->on('delivery_statuses')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('application_items')) {
            return;
        }

        Schema::table('application_items', function (Blueprint $table) {
            if (
                Schema::hasColumn('application_items', 'custom_equipment_supply_status_id')
                && $this->foreignKeyExists('application_items', 'application_items_custom_supply_status_fk')
            ) {
                $table->dropForeign('application_items_custom_supply_status_fk');
            }
            if (
                Schema::hasColumn('application_items', 'delivery_status_id')
                && $this->foreignKeyExists('application_items', 'application_items_delivery_status_fk')
            ) {
                $table->dropForeign('application_items_delivery_status_fk');
            }
        });
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        $database = $connection->getDatabaseName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', $database)
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', $constraintName)
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->exists();
        }

        if ($driver === 'pgsql') {
            return DB::table('information_schema.table_constraints')
                ->where('table_catalog', $database)
                ->where('table_name', $table)
                ->where('constraint_name', $constraintName)
                ->where('constraint_type', 'FOREIGN KEY')
                ->exists();
        }

        // Fallback for sqlite/other drivers in local tests.
        return false;
    }
};
