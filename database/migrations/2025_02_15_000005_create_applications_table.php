<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('applications')) {
            Schema::create('applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('source_application_id')->nullable()->constrained('applications')->nullOnDelete();
                $table->foreignId('subdivision_id')->constrained('subdivisions')->cascadeOnDelete();
                $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('equipment_type_id')->nullable()->constrained('equipment_types')->nullOnDelete();
                $table->string('equipment_name')->nullable();
                $table->string('equipment_in_warehouse')->nullable();
                $table->unsignedInteger('quantity');
                $table->date('desired_delivery_date');
                // Кто нажал «Сохранить согласование» (аудит; user_id — кто создал заявку)
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        } else {
            if (Schema::hasColumn('applications', 'warehouse_id')) {
                Schema::table('applications', function (Blueprint $table) {
                    $table->dropForeign(['warehouse_id']);
                });
            }
        }

        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'approved_at')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropColumn('approved_at');
            });
        }

        if (Schema::hasTable('applications') && ! Schema::hasColumn('applications', 'approved_by_user_id')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->foreignId('approved_by_user_id')
                    ->nullable()
                    ->after('desired_delivery_date')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
