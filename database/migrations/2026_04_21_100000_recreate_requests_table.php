<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('requests')) {
            Schema::table('requests', function (Blueprint $table) {
                if (! Schema::hasColumn('requests', 'recipient_user_id')) {
                    $table->foreignId('recipient_user_id')
                        ->nullable()
                        ->after('request_layout_id')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });

            return;
        }

        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('registry_number')->nullable()->unique();
            $table->json('data');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('request_layout_id')->constrained('request_layout')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
