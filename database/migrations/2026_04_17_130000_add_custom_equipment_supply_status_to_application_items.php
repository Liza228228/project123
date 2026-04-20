<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_items', function (Blueprint $table) {
            $table->string('custom_equipment_supply_status', 40)
                ->nullable()
                ->after('reason_not_selected');
        });

        DB::table('application_items')
            ->whereNotNull('equipment_id')
            ->update(['custom_equipment_supply_status' => null]);

        DB::table('application_items')
            ->whereNull('equipment_id')
            ->where('is_checked', true)
            ->update(['custom_equipment_supply_status' => 'accepted']);

        DB::table('application_items')
            ->whereNull('equipment_id')
            ->where('is_checked', false)
            ->update(['custom_equipment_supply_status' => 'pending_approval']);

        // Согласование с прежними кодами статусов (если миграция уже выполнялась в старой редакции).
        DB::table('application_items')
            ->where('custom_equipment_supply_status', 'awaiting_arrival')
            ->update(['custom_equipment_supply_status' => 'accepted']);

        DB::table('application_items')
            ->where('custom_equipment_supply_status', 'on_main_warehouse')
            ->update(['custom_equipment_supply_status' => 'on_warehouse']);
    }

    public function down(): void
    {
        Schema::table('application_items', function (Blueprint $table) {
            $table->dropColumn('custom_equipment_supply_status');
        });
    }
};
