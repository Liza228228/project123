<?php

use App\Models\MaterialStockMovementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('material_stock_movement_types')) {
            return;
        }

        $receiptId = DB::table('material_stock_movement_types')->where('name', 'Приход')->value('id');
        $issueId = DB::table('material_stock_movement_types')->where('name', 'Списание')->value('id');
        $adjustmentId = DB::table('material_stock_movement_types')->where('name', 'Корректировка')->value('id');

        if ($adjustmentId === null || $receiptId === null || $issueId === null) {
            return;
        }

        DB::table('material_stock_movements')
            ->where('material_stock_movement_type_id', $adjustmentId)
            ->where('quantity', '>', 0)
            ->update([
                'material_stock_movement_type_id' => $receiptId,
            ]);

        DB::table('material_stock_movements')
            ->where('material_stock_movement_type_id', $adjustmentId)
            ->where('quantity', '<', 0)
            ->update([
                'material_stock_movement_type_id' => $issueId,
                'quantity' => DB::raw('ABS(quantity)'),
            ]);

        DB::table('material_stock_movements')
            ->where('material_stock_movement_type_id', $adjustmentId)
            ->delete();

        DB::table('material_stock_movement_types')->where('id', $adjustmentId)->delete();

        MaterialStockMovementType::forgetIdCache();
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('material_stock_movement_types')) {
            return;
        }

        if (DB::table('material_stock_movement_types')->where('name', 'Корректировка')->exists()) {
            return;
        }

        DB::table('material_stock_movement_types')->insert([
            'id' => 3,
            'name' => 'Корректировка',
        ]);
    }
};
