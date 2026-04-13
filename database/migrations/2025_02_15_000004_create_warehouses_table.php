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
            $table->string('code', 10);
            $table->foreignId('subdivision_id')->nullable()->constrained('subdivisions')->nullOnDelete();
            $table->foreignId('warehouse_type_id')->nullable()->constrained('warehouse_types')->nullOnDelete();  
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
