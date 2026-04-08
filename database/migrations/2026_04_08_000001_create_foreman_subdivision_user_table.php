<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foreman_subdivision_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foreman_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subdivision_id')->constrained('subdivisions')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['foreman_user_id', 'subdivision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foreman_subdivision_user');
    }
};
