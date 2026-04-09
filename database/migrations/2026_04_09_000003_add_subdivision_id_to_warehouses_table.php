<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('subdivision_id')
                ->nullable()
                ->after('code')
                ->constrained('subdivisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subdivision_id');
        });
    }
};
