<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_stock_movements', function (Blueprint $table) {
            $table->unsignedTinyInteger('stock_bucket')->default(0)->after('receipt_variant');
        });

        DB::table('material_stock_movements')->update(['stock_bucket' => 0]);
    }

    public function down(): void
    {
        Schema::table('material_stock_movements', function (Blueprint $table) {
            $table->dropColumn('stock_bucket');
        });
    }
};
