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
            $table->foreignId('custom_target_warehouse_id')->nullable()->after('delivery_marked_at')->constrained('warehouses')->nullOnDelete();
            $table->boolean('custom_foreman_in_transit')->default(false)->after('custom_target_warehouse_id');
        });

        // Ранее «Заказано» без этапа «В пути» — переводим в новый статус, чтобы дальше была отметка «На складе».
        DB::table('application_items')
            ->whereNull('equipment_id')
            ->where('custom_equipment_supply_status', 'ordered')
            ->update(['custom_equipment_supply_status' => 'supply_in_transit']);
    }

    public function down(): void
    {
        Schema::table('application_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('custom_target_warehouse_id');
            $table->dropColumn('custom_foreman_in_transit');
        });
    }
};
