<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_equipment_supply_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
        });

        Schema::create('delivery_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
        });

        DB::table('custom_equipment_supply_statuses')->insert([
            ['id' => 1, 'name' => 'На согласовании'],
            ['id' => 2, 'name' => 'Принято по заявке'],
            ['id' => 3, 'name' => 'Заказано'],
            ['id' => 4, 'name' => 'В пути'],
            ['id' => 5, 'name' => 'На складе'],
        ]);

        DB::table('delivery_statuses')->insert([
            ['id' => 1, 'name' => 'В пути'],
            ['id' => 2, 'name' => 'Доставлено'],
        ]);

        Schema::table('application_items', function (Blueprint $table) {
            if (! Schema::hasColumn('application_items', 'custom_equipment_supply_status_id')) {
                $table->foreignId('custom_equipment_supply_status_id')
                    ->nullable()
                    ->after('custom_equipment_supply_status')
                    ->constrained('custom_equipment_supply_statuses')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('application_items', 'delivery_status_id')) {
                $table->foreignId('delivery_status_id')
                    ->nullable()
                    ->after('delivery_status')
                    ->constrained('delivery_statuses')
                    ->nullOnDelete();
            }
        });

        $customStatusIdByCode = [
            'pending_approval' => 1,
            'accepted' => 2,
            'ordered' => 3,
            'supply_in_transit' => 4,
            'on_warehouse' => 5,
            'awaiting_arrival' => 2,
            'on_main_warehouse' => 5,
        ];

        foreach ($customStatusIdByCode as $legacyCode => $newId) {
            DB::table('application_items')
                ->where('custom_equipment_supply_status', $legacyCode)
                ->update(['custom_equipment_supply_status_id' => $newId]);
        }

        $deliveryStatusIdByCode = [
            'in_transit' => 1,
            'delivered' => 2,
        ];

        foreach ($deliveryStatusIdByCode as $legacyCode => $newId) {
            DB::table('application_items')
                ->where('delivery_status', $legacyCode)
                ->update(['delivery_status_id' => $newId]);
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
                $table->string('custom_equipment_supply_status', 40)->nullable()->after('reason_not_selected');
            }
            if (! Schema::hasColumn('application_items', 'delivery_status')) {
                $table->string('delivery_status', 30)->nullable()->after('reason_boiler_chief_not_selected');
            }
        });

        DB::table('application_items')
            ->where('custom_equipment_supply_status_id', 1)
            ->update(['custom_equipment_supply_status' => 'pending_approval']);
        DB::table('application_items')
            ->where('custom_equipment_supply_status_id', 2)
            ->update(['custom_equipment_supply_status' => 'accepted']);
        DB::table('application_items')
            ->where('custom_equipment_supply_status_id', 3)
            ->update(['custom_equipment_supply_status' => 'ordered']);
        DB::table('application_items')
            ->where('custom_equipment_supply_status_id', 4)
            ->update(['custom_equipment_supply_status' => 'supply_in_transit']);
        DB::table('application_items')
            ->where('custom_equipment_supply_status_id', 5)
            ->update(['custom_equipment_supply_status' => 'on_warehouse']);

        DB::table('application_items')
            ->where('delivery_status_id', 1)
            ->update(['delivery_status' => 'in_transit']);
        DB::table('application_items')
            ->where('delivery_status_id', 2)
            ->update(['delivery_status' => 'delivered']);

        Schema::table('application_items', function (Blueprint $table) {
            if (Schema::hasColumn('application_items', 'delivery_status_id')) {
                $table->dropConstrainedForeignId('delivery_status_id');
            }
            if (Schema::hasColumn('application_items', 'custom_equipment_supply_status_id')) {
                $table->dropConstrainedForeignId('custom_equipment_supply_status_id');
            }
        });

        Schema::dropIfExists('delivery_statuses');
        Schema::dropIfExists('custom_equipment_supply_statuses');
    }
};
