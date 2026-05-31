<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('material_stock_movements', 'stock_bucket')) {
            return;
        }

        DB::table('material_stock_movements')->where('stock_bucket', 'good')->update(['stock_bucket' => '0']);
        DB::table('material_stock_movements')->where('stock_bucket', 'defective')->update(['stock_bucket' => '1']);
        DB::table('material_stock_movements')
            ->where(function ($query): void {
                $query->whereNull('stock_bucket')->orWhere('stock_bucket', '');
            })
            ->update(['stock_bucket' => '0']);

        Schema::table('material_stock_movements', function (Blueprint $table) {
            $table->unsignedTinyInteger('stock_bucket')->default(0)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('material_stock_movements', 'stock_bucket')) {
            return;
        }

        Schema::table('material_stock_movements', function (Blueprint $table) {
            $table->string('stock_bucket', 20)->default('good')->change();
        });

        DB::table('material_stock_movements')->whereIn('stock_bucket', ['0', 0])->update(['stock_bucket' => 'good']);
        DB::table('material_stock_movements')->whereIn('stock_bucket', ['1', 1])->update(['stock_bucket' => 'defective']);
    }
};
