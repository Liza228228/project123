<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_change_journal') || ! Schema::hasColumn('application_change_journal', 'action')) {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM application_change_journal WHERE Field = 'action'");
        $columnType = strtolower((string) ($column->Type ?? ''));

        if (! str_contains($columnType, 'varchar') && ! str_contains($columnType, 'char')) {
            return;
        }

        DB::table('application_change_journal')->where('action', 'added')->update(['action' => '0']);
        DB::table('application_change_journal')->where('action', 'updated')->update(['action' => '1']);
        DB::table('application_change_journal')->where('action', 'removed')->update(['action' => '2']);

        DB::statement('ALTER TABLE application_change_journal MODIFY action TINYINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('application_change_journal') || ! Schema::hasColumn('application_change_journal', 'action')) {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM application_change_journal WHERE Field = 'action'");
        $columnType = strtolower((string) ($column->Type ?? ''));

        if (! str_contains($columnType, 'tinyint')) {
            return;
        }

        DB::statement('ALTER TABLE application_change_journal MODIFY action VARCHAR(32) NOT NULL');

        DB::table('application_change_journal')->where('action', '0')->update(['action' => 'added']);
        DB::table('application_change_journal')->where('action', '1')->update(['action' => 'updated']);
        DB::table('application_change_journal')->where('action', '2')->update(['action' => 'removed']);
    }
};
