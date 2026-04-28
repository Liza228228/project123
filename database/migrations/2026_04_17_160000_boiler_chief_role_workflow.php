<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boiler_chief_subdivision_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boiler_chief_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subdivision_id')->constrained('subdivisions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['boiler_chief_user_id', 'subdivision_id'], 'bc_sub_user_unique');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('boiler_chief_subdivision_user');
    }
};
