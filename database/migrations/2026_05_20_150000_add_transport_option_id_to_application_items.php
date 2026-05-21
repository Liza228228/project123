<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_items') || Schema::hasColumn('application_items', 'transport_option_id')) {
            return;
        }

        Schema::table('application_items', function (Blueprint $table) {
            $table->foreignId('transport_option_id')
                ->nullable()
                ->after('delivery_warehouse_id')
                ->constrained('transport_options')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('application_items', 'transport_option_id')) {
            return;
        }

        Schema::table('application_items', function (Blueprint $table) {
            $table->dropForeign(['transport_option_id']);
            $table->dropColumn('transport_option_id');
        });
    }
};
