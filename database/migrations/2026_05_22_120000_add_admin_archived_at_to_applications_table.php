<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->timestamp('admin_archived_at')->nullable()->after('archived_at');
            $table->index('admin_archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex(['admin_archived_at']);
            $table->dropColumn('admin_archived_at');
        });
    }
};
