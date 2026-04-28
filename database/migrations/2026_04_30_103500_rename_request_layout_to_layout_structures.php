<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Для БД, где уже создана таблица request_layout старыми версиями миграций.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('layout_structures') || ! Schema::hasTable('request_layout')) {
            return;
        }

        Schema::rename('request_layout', 'layout_structures');

        if (Schema::hasTable('requests') && Schema::hasColumn('requests', 'request_layout_id')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->dropForeign(['request_layout_id']);
            });
            Schema::table('requests', function (Blueprint $table) {
                $table->renameColumn('request_layout_id', 'layout_structure_id');
            });
            Schema::table('requests', function (Blueprint $table) {
                $table->foreign('layout_structure_id')
                    ->references('id')
                    ->on('layout_structures')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('layout_structures') || Schema::hasTable('request_layout')) {
            return;
        }

        if (Schema::hasTable('requests') && Schema::hasColumn('requests', 'layout_structure_id')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->dropForeign(['layout_structure_id']);
            });
            Schema::table('requests', function (Blueprint $table) {
                $table->renameColumn('layout_structure_id', 'request_layout_id');
            });
        }

        Schema::rename('layout_structures', 'request_layout');

        if (Schema::hasTable('requests') && Schema::hasColumn('requests', 'request_layout_id')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->foreign('request_layout_id')
                    ->references('id')
                    ->on('request_layout')
                    ->cascadeOnDelete();
            });
        }
    }
};
