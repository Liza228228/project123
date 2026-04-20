<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('address', 255)->nullable()->after('code');
            $table->string('address_postal_code', 20)->nullable()->after('address');
            $table->string('address_region', 150)->nullable()->after('address_postal_code');
            $table->string('address_city', 150)->nullable()->after('address_region');
            $table->string('address_street', 150)->nullable()->after('address_city');
            $table->string('address_house', 50)->nullable()->after('address_street');
            $table->string('address_block', 50)->nullable()->after('address_house');
            $table->string('address_flat', 50)->nullable()->after('address_block');
            $table->string('address_fias_id', 50)->nullable()->after('address_flat');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn([
                'address_fias_id',
                'address_flat',
                'address_block',
                'address_house',
                'address_street',
                'address_city',
                'address_region',
                'address_postal_code',
                'address',
            ]);
        });
    }
};
