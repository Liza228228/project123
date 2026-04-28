<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('layout_structures')) {
            return;
        }

        Schema::table('layout_structures', function (Blueprint $table) {
            if (! Schema::hasColumn('layout_structures', 'document_header_layout_id')) {
                $table->foreignId('document_header_layout_id')
                    ->nullable()
                    ->after('division_assigner_id')
                    ->constrained('document_header_layouts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('layout_structures')) {
            return;
        }

        Schema::table('layout_structures', function (Blueprint $table) {
            if (Schema::hasColumn('layout_structures', 'document_header_layout_id')) {
                $table->dropForeign(['document_header_layout_id']);
                $table->dropColumn('document_header_layout_id');
            }
        });
    }
};
