<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('custom_equipment_supply_statuses')) {
            DB::table('custom_equipment_supply_statuses')->upsert([
                ['id' => 1, 'name' => 'На согласовании'],
                ['id' => 2, 'name' => 'Принято по заявке'],
                ['id' => 3, 'name' => 'Заказано'],
                ['id' => 4, 'name' => 'В пути'],
                ['id' => 5, 'name' => 'На складе'],
            ], ['id'], ['name']);
        }

        if (Schema::hasTable('delivery_statuses')) {
            DB::table('delivery_statuses')->upsert([
                ['id' => 1, 'name' => 'В пути'],
                ['id' => 2, 'name' => 'Доставлено'],
            ], ['id'], ['name']);
        }

        if (Schema::hasColumn('application_items', 'custom_equipment_supply_status')
            && Schema::hasColumn('application_items', 'custom_equipment_supply_status_id')) {
            DB::table('application_items')
                ->where('custom_equipment_supply_status', 'pending_approval')
                ->update(['custom_equipment_supply_status_id' => 1]);
            DB::table('application_items')
                ->whereIn('custom_equipment_supply_status', ['accepted', 'awaiting_arrival'])
                ->update(['custom_equipment_supply_status_id' => 2]);
            DB::table('application_items')
                ->where('custom_equipment_supply_status', 'ordered')
                ->update(['custom_equipment_supply_status_id' => 3]);
            DB::table('application_items')
                ->where('custom_equipment_supply_status', 'supply_in_transit')
                ->update(['custom_equipment_supply_status_id' => 4]);
            DB::table('application_items')
                ->whereIn('custom_equipment_supply_status', ['on_warehouse', 'on_main_warehouse'])
                ->update(['custom_equipment_supply_status_id' => 5]);
        }

        if (Schema::hasColumn('application_items', 'delivery_status')
            && Schema::hasColumn('application_items', 'delivery_status_id')) {
            DB::table('application_items')
                ->where('delivery_status', 'in_transit')
                ->update(['delivery_status_id' => 1]);
            DB::table('application_items')
                ->where('delivery_status', 'delivered')
                ->update(['delivery_status_id' => 2]);
        }

        Schema::table('application_items', function (Blueprint $table) {
            if (Schema::hasColumn('application_items', 'custom_equipment_supply_status')) {
                $table->dropColumn('custom_equipment_supply_status');
            }
            if (Schema::hasColumn('application_items', 'delivery_status')) {
                $table->dropColumn('delivery_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('application_items', function (Blueprint $table) {
            if (! Schema::hasColumn('application_items', 'custom_equipment_supply_status')) {
                $table->string('custom_equipment_supply_status', 40)->nullable();
            }
            if (! Schema::hasColumn('application_items', 'delivery_status')) {
                $table->string('delivery_status', 30)->nullable();
            }
        });
    }
};
