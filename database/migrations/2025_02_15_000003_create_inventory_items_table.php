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
            $table->foreignId('parent_id')->nullable()->constrained('inventory_items')->cascadeOnDelete()->comment('Родитель (иерархия из файла 1)');
            $table->boolean('is_group')->default(false)->comment('Это группа (Да/Нет)');
            $table->string('name')->comment('Наименование');
            $table->string('code')->comment('Код');
            $table->foreignId('warehouse_type_id')->nullable()->constrained('warehouse_types')->nullOnDelete()->comment('Тип склада');
            $table->text('comment')->nullable()->comment('Комментарий');
            $table->timestamps();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
