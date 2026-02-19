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
            $table->foreignId('equipment_type_id')->nullable()->constrained('equipment_types')->nullOnDelete();
            $table->string('equipment_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_checked')->default(false);
            $table->string('reason_not_selected', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['equipment_type_id']);
            $table->dropColumn(['equipment_type_id', 'equipment_name', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_items');
    }
};
