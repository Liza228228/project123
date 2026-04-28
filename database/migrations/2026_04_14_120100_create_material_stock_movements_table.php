<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_stock_movement_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
        });

        DB::table('material_stock_movement_types')->insert([
            ['id' => 1, 'name' => 'Приход'],
            ['id' => 2, 'name' => 'Списание'],
            ['id' => 3, 'name' => 'Корректировка'],
        ]);

        Schema::create('material_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('material_stock_movement_type_id')
                ->constrained('material_stock_movement_types')
                ->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->string('counterparty', 255)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['equipment_id', 'warehouse_id', 'created_at'], 'msm_eq_wh_created_idx');
            $table->index(['material_stock_movement_type_id', 'created_at'], 'msm_type_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stock_movements');
        Schema::dropIfExists('material_stock_movement_types');
    }
};
