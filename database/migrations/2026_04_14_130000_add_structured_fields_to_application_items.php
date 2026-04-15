<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_items', function (Blueprint $table) {
            $table->string('base_name', 255)->nullable()->after('equipment_name');
            $table->string('size_value', 120)->nullable()->after('base_name');
            $table->string('measurement_type', 20)->default('piece')->after('quantity');
            $table->string('quantity_unit', 20)->default('шт')->after('quantity');
            $table->string('raw_input', 255)->nullable()->after('quantity_unit');
        });
    }

    public function down(): void
    {
        Schema::table('application_items', function (Blueprint $table) {
            $table->dropColumn(['base_name', 'size_value', 'quantity_unit', 'raw_input']);
        });
    }
};
