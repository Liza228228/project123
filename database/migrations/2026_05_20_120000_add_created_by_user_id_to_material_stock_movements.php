<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_stock_movements')) {
            return;
        }

        if (Schema::hasColumn('material_stock_movements', 'created_by_user_id')) {
            return;
        }

        Schema::table('material_stock_movements', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('comment')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_stock_movements')) {
            return;
        }

        if (! Schema::hasColumn('material_stock_movements', 'created_by_user_id')) {
            return;
        }

        Schema::table('material_stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
