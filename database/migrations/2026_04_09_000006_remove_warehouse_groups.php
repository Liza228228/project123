<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasGroupColumn = in_array('warehouse_group_id', Schema::getColumnListing('subdivisions'), true);

        if ($hasGroupColumn) {
            try {
                Schema::table('subdivisions', function (Blueprint $table) {
                    $table->dropForeign(['warehouse_group_id']);
                });
            } catch (\Throwable) {
            }

            try {
                Schema::table('subdivisions', function (Blueprint $table) {
                    $table->dropColumn('warehouse_group_id');
                });
            } catch (\Throwable) {
            }
        }

        Schema::dropIfExists('warehouse_groups');

        $hasNameUnique = collect(Schema::getIndexes('subdivisions'))
            ->contains(fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['name']);

        if (! $hasNameUnique) {
            Schema::table('subdivisions', function (Blueprint $table) {
                $table->unique('name');
            });
        }
    }

    public function down(): void {}
};
