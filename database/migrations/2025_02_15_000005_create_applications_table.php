<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('applications')) {
            Schema::create('applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subdivision_id')->constrained('subdivisions')->cascadeOnDelete();
                $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('equipment_type_id')->nullable()->constrained('equipment_types')->nullOnDelete();
                $table->string('equipment_name')->nullable();
                $table->string('equipment_in_warehouse')->nullable();
                $table->unsignedInteger('quantity');
                $table->date('desired_delivery_date');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        } else {
            if (Schema::hasColumn('applications', 'warehouse_id')) {
                Schema::table('applications', function (Blueprint $table) {
                    $table->dropForeign(['warehouse_id']);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
