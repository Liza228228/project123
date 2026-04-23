<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_delivery_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate', 30)->unique();
            $table->string('label', 100)->nullable();
            $table->timestamps();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('delivery_vehicle_plate', 30)->nullable()->after('transport_option_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('delivery_vehicle_plate');
        });

        Schema::dropIfExists('company_delivery_vehicles');
    }
};
