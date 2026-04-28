<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_options', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('plate', 30)->nullable()->unique();
            $table->string('label', 100)->nullable();
            $table->timestamps();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->foreign('transport_option_id')
                ->references('id')
                ->on('transport_options')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['transport_option_id']);
        });

        Schema::dropIfExists('transport_options');
    }
};
