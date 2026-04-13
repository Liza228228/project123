<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_items', function (Blueprint $table) {
            $table->boolean('is_checked')->default(false)->after('quantity');
            $table->text('reason_not_selected')->nullable()->after('is_checked');
        });
    }

    public function down(): void
    {
        Schema::table('application_items', function (Blueprint $table) {
            $table->dropColumn(['is_checked', 'reason_not_selected']);
        });
    }
};
