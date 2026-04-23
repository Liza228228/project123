<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_items', function (Blueprint $table) {
            $table->string('delivery_status', 30)->nullable()->after('reason_boiler_chief_not_selected');
            $table->foreignId('delivery_warehouse_id')->nullable()->after('delivery_status')->constrained('warehouses')->nullOnDelete();
            $table->foreignId('delivery_marked_by_user_id')->nullable()->after('delivery_warehouse_id')->constrained('users')->nullOnDelete();
            $table->timestamp('delivery_marked_at')->nullable()->after('delivery_marked_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('application_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_marked_by_user_id');
            $table->dropConstrainedForeignId('delivery_warehouse_id');
            $table->dropColumn(['delivery_status', 'delivery_marked_at']);
        });
    }
};
