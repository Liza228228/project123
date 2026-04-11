<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Ранее добавляла slug; оставлена пустой, чтобы не ломать порядок записей в migrations.
 * Колонка slug удаляется миграцией 2026_04_12_000000_drop_slug_from_roles_table.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
