<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_stock_movements', function (Blueprint $table) {
            $table->string('receipt_variant', 120)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('material_stock_movements', function (Blueprint $table) {
            $table->dropColumn('receipt_variant');
        });
    }
};
