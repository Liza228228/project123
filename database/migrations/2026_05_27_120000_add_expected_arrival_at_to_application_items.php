<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_items', function (Blueprint $table): void {
            $table->date('expected_arrival_at')
                ->nullable()
                ->after('transport_option_id');
        });
    }

    public function down(): void
    {
        Schema::table('application_items', function (Blueprint $table): void {
            $table->dropColumn('expected_arrival_at');
        });
    }
};
