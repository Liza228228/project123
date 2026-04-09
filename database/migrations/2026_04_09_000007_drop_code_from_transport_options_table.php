<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transport_options') && Schema::hasColumn('transport_options', 'code')) {
            Schema::table('transport_options', function (Blueprint $table) {
                $table->dropColumn('code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transport_options') && ! Schema::hasColumn('transport_options', 'code')) {
            Schema::table('transport_options', function (Blueprint $table) {
                $table->string('code', 64)->nullable()->unique()->after('id');
            });
        }
    }
};
