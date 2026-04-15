<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('database_operation_logs')) {
            return;
        }

        Schema::create('database_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operation_type', 30);
            $table->string('status', 20);
            $table->string('file_name', 255);
            $table->string('storage_path', 500)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['operation_type', 'executed_at'], 'db_op_logs_type_date_idx');
            $table->index(['status', 'executed_at'], 'db_op_logs_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_operation_logs');
    }
};
