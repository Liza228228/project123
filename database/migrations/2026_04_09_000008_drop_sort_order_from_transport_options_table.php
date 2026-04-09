<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transport_options') && Schema::hasColumn('transport_options', 'sort_order')) {
            Schema::table('transport_options', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transport_options') && ! Schema::hasColumn('transport_options', 'sort_order')) {
            Schema::table('transport_options', function (Blueprint $table) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('name');
            });
        }
    }
};
