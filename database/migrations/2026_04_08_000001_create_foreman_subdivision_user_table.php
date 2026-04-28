<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Связь мастера участка (роль «мастер») с подразделением: на каких объектах он может вести заявки.
         *
         * @see \App\Models\User::assignedSubdivisions()
         * @see \App\Models\Subdivision::siteForemen()
         */
        Schema::create('foreman_subdivision_user', function (Blueprint $table) {
            $table->id();
            // Пользователь-мастер участка (users.id)
            $table->foreignId('foreman_user_id')->constrained('users')->cascadeOnDelete();
            // Подразделение участка (subdivisions.id)
            $table->foreignId('subdivision_id')->constrained('subdivisions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['foreman_user_id', 'subdivision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foreman_subdivision_user');
    }
};
