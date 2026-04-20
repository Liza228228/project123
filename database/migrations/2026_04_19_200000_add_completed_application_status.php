<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_statuses')) {
            return;
        }

        if (DB::table('application_statuses')->where('code', 'completed')->exists()) {
            return;
        }

        $now = now();
        DB::table('application_statuses')->insert([
            'code' => 'completed',
            'name' => 'Выполнена',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('application_statuses')) {
            return;
        }

        $completedId = DB::table('application_statuses')->where('code', 'completed')->value('id');
        if ($completedId === null) {
            return;
        }

        if (Schema::hasTable('applications')) {
            $approvedId = DB::table('application_statuses')->where('code', 'approved')->value('id');
            if ($approvedId !== null) {
                DB::table('applications')
                    ->where('application_status_id', $completedId)
                    ->update(['application_status_id' => $approvedId]);
            }
        }

        DB::table('application_statuses')->where('code', 'completed')->delete();
    }
};
