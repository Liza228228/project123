<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_primary')->default(false); // приоритет да нет
            $table->string('name', 150);
            $table->string('address_postal_code', 20)->nullable();
            $table->string('address_region', 150)->nullable();
            $table->string('address_city', 150)->nullable();
            $table->string('address_street', 150)->nullable();
            $table->string('address_house', 50)->nullable();
            $table->string('address_block', 50)->nullable();
            $table->string('address_flat', 50)->nullable();
            $table->string('address_fias_id', 50)->nullable();
            $table->foreignId('subdivision_id')->nullable()->constrained('subdivisions')->nullOnDelete();
            $table->foreignId('warehouse_type_id')->nullable()->constrained('warehouse_types')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
