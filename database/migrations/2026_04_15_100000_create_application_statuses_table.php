<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        $now = now();
        foreach (
            [
                'На согласовании',
                'Согласована',
                'Не согласована',
                'Частично согласована',
            ] as $name
        ) {
            DB::table('application_statuses')->insert([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $pendingId = (int) DB::table('application_statuses')->where('name', 'На согласовании')->value('id');

        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'application_status_id')) {
            DB::table('applications')
                ->whereNull('application_status_id')
                ->update(['application_status_id' => $pendingId]);

            Schema::table('applications', function (Blueprint $table) {
                $table->foreign('application_status_id')
                    ->references('id')
                    ->on('application_statuses')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropForeign(['application_status_id']);
            });
        }

        Schema::dropIfExists('application_statuses');
    }
};
