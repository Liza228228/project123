<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('applications') || ! Schema::hasColumn('applications', 'delivery_vehicle_plate')) {
            return;
        }

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn('delivery_vehicle_plate');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('applications') || Schema::hasColumn('applications', 'delivery_vehicle_plate')) {
            return;
        }

        Schema::table('applications', function (Blueprint $table): void {
            $table->string('delivery_vehicle_plate', 32)->nullable()->after('transport_option_id');
        });
    }
};
