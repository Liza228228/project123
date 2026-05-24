<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('equipment')) {
            return;
        }

        Schema::table('equipment', function (Blueprint $table) {
            if (! Schema::hasColumn('equipment', 'unit_type_id')) {
                $table->foreignId('unit_type_id')
                    ->nullable()
                    ->after('measurement_unit_id')
                    ->constrained('unit_types')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('equipment', 'measurement_unit_id')) {
            DB::table('equipment')
                ->whereNotNull('measurement_unit_id')
                ->whereNull('unit_type_id')
                ->update([
                    'unit_type_id' => DB::raw(
                        '(SELECT unit_type_id FROM measurement_units WHERE measurement_units.id = equipment.measurement_unit_id)'
                    ),
                ]);
        }

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['name', 'unit_type_id'], 'equipment_name_unit_type_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('equipment')) {
            return;
        }

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropUnique('equipment_name_unit_type_unique');
            $table->unique('name');
        });

        Schema::table('equipment', function (Blueprint $table) {
            if (Schema::hasColumn('equipment', 'unit_type_id')) {
                $table->dropConstrainedForeignId('unit_type_id');
            }
        });
    }
};
