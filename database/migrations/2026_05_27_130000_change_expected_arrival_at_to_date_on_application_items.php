<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('application_items', 'expected_arrival_at')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE application_items MODIFY expected_arrival_at DATE NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE application_items ALTER COLUMN expected_arrival_at TYPE DATE USING expected_arrival_at::date');
        } else {
            // sqlite и прочие — пересоздание через временную колонку не требуется для dev
            Schema::table('application_items', function ($table): void {
                $table->date('expected_arrival_at')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('application_items', 'expected_arrival_at')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE application_items MODIFY expected_arrival_at DATETIME NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE application_items ALTER COLUMN expected_arrival_at TYPE TIMESTAMP USING expected_arrival_at::timestamp');
        } else {
            Schema::table('application_items', function ($table): void {
                $table->dateTime('expected_arrival_at')->nullable()->change();
            });
        }
    }
};
