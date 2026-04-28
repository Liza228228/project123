<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('layout_structures')) {
            return;
        }

        if (Schema::hasTable('request_layout')) {
            return;
        }

        Schema::create('layout_structures', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->json('schema');
            $table->boolean('has_header')->default(false);
            $table->string('type', 32)->default('pdf');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('division_assigner_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_structures');
    }
};
