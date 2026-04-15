<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('measurement_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_type_id')->constrained('unit_types')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 50);
            $table->boolean('is_base')->default(false);
            $table->decimal('multiplier_to_base', 12, 4)->default(1);
            $table->timestamps();

            $table->unique(['unit_type_id', 'code'], 'mu_type_code_unique');
        });

        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('base_name', 120)->nullable();
            $table->string('size_value', 120)->nullable();
            $table->foreignId('measurement_unit_id')->nullable()->constrained('measurement_units')->nullOnDelete();
            $table->timestamps();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('measurement_units');
        Schema::dropIfExists('unit_types');
    }
};
