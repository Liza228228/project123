<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subdivision_id')->constrained('subdivisions')->cascadeOnDelete()->comment('Подразделение');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Ответственный');
            $table->foreignId('equipment_type_id')->nullable()->constrained('equipment_types')->nullOnDelete()->comment('Тип оборудования из справочника');
            $table->string('equipment_name')->nullable()->comment('Оборудование (свободный ввод)');
            $table->string('equipment_in_warehouse')->nullable()->comment('Оборудование на складе (пока пусто)');
            $table->unsignedInteger('quantity')->comment('Количество оборудования');
            $table->date('desired_delivery_date')->comment('Желаемая дата поставки');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('Автор заявки (мастер участка)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
