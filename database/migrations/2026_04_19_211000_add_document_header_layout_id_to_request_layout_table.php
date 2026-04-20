<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_layout', function (Blueprint $table) {
            if (! Schema::hasColumn('request_layout', 'document_header_layout_id')) {
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
        Schema::table('request_layout', function (Blueprint $table) {
            if (Schema::hasColumn('request_layout', 'document_header_layout_id')) {
                $table->dropForeign(['document_header_layout_id']);
                $table->dropColumn('document_header_layout_id');
            }
        });
    }
};
