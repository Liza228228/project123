<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_stock_movements')) {
            return;
        }

        if (Schema::hasColumn('material_stock_movements', 'material_stock_movement_type_id')) {
            return;
        }

        if (! Schema::hasTable('material_stock_movement_types')) {
            Schema::create('material_stock_movement_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
            });

            DB::table('material_stock_movement_types')->insert([
                ['id' => 1, 'name' => 'Приход'],
                ['id' => 2, 'name' => 'Списание'],
                ['id' => 3, 'name' => 'Корректировка'],
            ]);
        }

        $this->dropIndexIfExists('material_stock_movements', 'msm_eq_wh_date_idx');
        $this->dropIndexIfExists('material_stock_movements', 'msm_type_date_idx');

        Schema::table('material_stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('material_stock_movement_type_id')->nullable()->after('warehouse_id');
        });

        $typeIds = DB::table('material_stock_movement_types')->pluck('id', 'name')->all();

        $oldTypeToName = [
            'receipt' => 'Приход',
            'issue' => 'Списание',
            'adjustment' => 'Корректировка',
        ];

        foreach ($oldTypeToName as $oldCode => $name) {
            if (isset($typeIds[$name])) {
                DB::table('material_stock_movements')
                    ->where('type', $oldCode)
                    ->update(['material_stock_movement_type_id' => (int) $typeIds[$name]]);
            }
        }

        DB::table('material_stock_movements')
            ->whereNull('material_stock_movement_type_id')
            ->update(['material_stock_movement_type_id' => (int) ($typeIds['Корректировка'] ?? 3)]);

        if (Schema::hasColumn('material_stock_movements', 'document_ref')) {
            $rows = DB::table('material_stock_movements')
                ->whereNotNull('document_ref')
                ->get(['id', 'document_ref', 'comment']);

            foreach ($rows as $row) {
                $ref = (string) $row->document_ref;
                $body = $row->comment !== null ? trim((string) $row->comment) : '';
                $packed = '__CORR__:'.$ref.($body !== '' ? "\n".$body : '');
                DB::table('material_stock_movements')->where('id', $row->id)->update(['comment' => $packed]);
            }
        }

        Schema::table('material_stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('material_stock_movements', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
            }
        });

        Schema::table('material_stock_movements', function (Blueprint $table) {
            $table->dropColumn(['type', 'happened_at', 'document_ref']);
        });

        Schema::table('material_stock_movements', function (Blueprint $table) {
            $table->foreign('material_stock_movement_type_id')
                ->references('id')
                ->on('material_stock_movement_types')
                ->restrictOnDelete();
        });

        if (! $this->indexExists('material_stock_movements', 'msm_eq_wh_created_idx')) {
            Schema::table('material_stock_movements', function (Blueprint $table) {
                $table->index(['equipment_id', 'warehouse_id', 'created_at'], 'msm_eq_wh_created_idx');
            });
        }
        if (! $this->indexExists('material_stock_movements', 'msm_type_created_idx')) {
            Schema::table('material_stock_movements', function (Blueprint $table) {
                $table->index(['material_stock_movement_type_id', 'created_at'], 'msm_type_created_idx');
            });
        }
    }

    public function down(): void
    {
        // Откат вручную при необходимости: схема сильно менялась.
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($i) => ($i->name ?? '') === $indexName);
        }

        $db = $connection->getDatabaseName();
        $row = $connection->selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$db, $table, $indexName]
        );

        return isset($row->c) && (int) $row->c > 0;
    }
};
