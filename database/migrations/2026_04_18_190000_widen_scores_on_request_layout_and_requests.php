<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Ранее расширяла DECIMAL для колонок scores. Колонки scores удалены
 * (см. 2026_04_19_210000_drop_scores_from_request_layout_and_requests_tables).
 * Миграция оставлена пустой, чтобы не ломать уже применённые записи в таблице migrations.
 */
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
