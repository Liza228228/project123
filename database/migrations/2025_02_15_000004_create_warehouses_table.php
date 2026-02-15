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
            $table->boolean('is_primary')->default(false); //приоритет да нет
            $table->string('name');
            $table->string('code');
            $table->foreignId('warehouse_type_id')->nullable()->constrained('warehouse_types')->nullOnDelete(); // тип склада (оптовый склад)
            $table->foreignId('retail_price_type_id')->nullable()->constrained('retail_price_types')->nullOnDelete()->comment('Тип цен розничной торговли');
            $table->text('comment')->nullable()->comment('Комментарий');
            $table->timestamps();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
