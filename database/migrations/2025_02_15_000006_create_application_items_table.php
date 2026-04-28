<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_checked')->default(false);
            $table->text('reason_not_selected')->nullable();
            $table->unsignedBigInteger('custom_equipment_supply_status_id')->nullable();
            $table->unsignedBigInteger('delivery_status_id')->nullable();
            $table->foreignId('delivery_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_items');
    }
};
