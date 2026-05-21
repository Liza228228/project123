<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('applications') && ! Schema::hasColumn('applications', 'management_supply_items_saved_at')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->timestamp('management_supply_items_saved_at')->nullable()->after('approved_by_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('applications', 'management_supply_items_saved_at')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropColumn('management_supply_items_saved_at');
            });
        }
    }
};
