<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Раньше создавала requests без softDeletes при отсутствии таблицы.
 * Схема — в 2026_04_18_120000_create_requests_table; здесь только аварийное создание.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('requests')) {
            return;
        }

        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('layout_structure_id')->constrained('layout_structures')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        //
    }
};
