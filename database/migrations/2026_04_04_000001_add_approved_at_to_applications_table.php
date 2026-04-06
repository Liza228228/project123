<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('application_director_changes');

        Schema::table('applications', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('desired_delivery_date');
            $table->timestamp('director_last_edited_at')->nullable();
            $table->foreignId('director_last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('director_last_edit_detail')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['director_last_edited_by']);
            $table->dropColumn(['director_last_edited_at', 'director_last_edited_by', 'director_last_edit_detail', 'approved_at']);
        });
    }
};
