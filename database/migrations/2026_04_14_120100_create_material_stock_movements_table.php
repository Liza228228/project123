<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('material_stock_movements')) {
            return;
        }

        Schema::create('material_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->enum('type', ['receipt', 'issue', 'adjustment']);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->dateTime('happened_at');
            $table->string('document_ref', 100)->nullable();
            $table->string('counterparty', 255)->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['equipment_id', 'warehouse_id', 'happened_at'], 'msm_eq_wh_date_idx');
            $table->index(['type', 'happened_at'], 'msm_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stock_movements');
    }
};
