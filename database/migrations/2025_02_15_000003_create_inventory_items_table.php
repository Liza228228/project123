<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('inventory_items')->cascadeOnDelete();
            $table->boolean('is_group')->default(false);
            $table->string('name');
            $table->string('code');
            $table->foreignId('warehouse_type_id')->nullable()->constrained('warehouse_types')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
