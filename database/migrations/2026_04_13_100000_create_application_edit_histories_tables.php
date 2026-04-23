<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('application_edit_histories');
    }

    public function down(): void
    {
        Schema::create('application_edit_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at');
            $table->text('equipment_change')->nullable();
            $table->text('change_reason')->nullable();
            $table->timestamps();
        });
    }
};
