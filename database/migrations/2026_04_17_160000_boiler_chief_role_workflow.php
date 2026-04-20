<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boiler_chief_subdivision_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boiler_chief_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subdivision_id')->constrained('subdivisions')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['boiler_chief_user_id', 'subdivision_id'], 'bc_sub_user_unique');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->timestamp('boiler_chief_stage_completed_at')->nullable();
        });

        Schema::table('application_items', function (Blueprint $table) {
            $table->boolean('boiler_chief_checked')->default(false);
            $table->string('reason_boiler_chief_not_selected', 500)->nullable();
        });

        DB::table('application_items')->update(['boiler_chief_checked' => true]);

        foreach (DB::table('applications')->select('id', 'created_at')->orderBy('id')->cursor() as $row) {
            DB::table('applications')->where('id', $row->id)->update([
                'boiler_chief_stage_completed_at' => $row->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('application_items', function (Blueprint $table) {
            $table->dropColumn(['boiler_chief_checked', 'reason_boiler_chief_not_selected']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('boiler_chief_stage_completed_at');
        });

        Schema::dropIfExists('boiler_chief_subdivision_user');
    }
};
