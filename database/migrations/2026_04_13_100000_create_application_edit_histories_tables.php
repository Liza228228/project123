<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_edit_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at');
            $table->timestamps();
        });

        Schema::create('application_edit_history_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_edit_history_id');
            $table->foreign('application_edit_history_id', 'ae_hist_lines_hist_fk')
                ->references('id')
                ->on('application_edit_histories')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_edit_history_lines');
        Schema::dropIfExists('application_edit_histories');
    }
};
