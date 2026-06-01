<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_item_manual_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_item_id')->unique()->constrained('application_items')->cascadeOnDelete();
            $table->string('equipment_name', 255)->nullable();
            $table->string('size_value', 120)->nullable();
            $table->string('measurement_type', 20)->default('piece');
            $table->string('quantity_unit', 20)->default('шт');
            $table->string('raw_input', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_item_manual_details');
    }
};
