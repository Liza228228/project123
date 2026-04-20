<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_layout', function (Blueprint $table) {
            if (! Schema::hasColumn('request_layout', 'has_header')) {
                $table->boolean('has_header')->default(false)->after('schema');
            }
            if (! Schema::hasColumn('request_layout', 'type')) {
                $table->string('type', 32)->default('pdf')->after('has_header');
            }
            if (! Schema::hasColumn('request_layout', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('type');
            }
            if (! Schema::hasColumn('request_layout', 'approver_id')) {
                $table->foreignId('approver_id')->nullable()->after('version')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('request_layout', 'user_assigner_id')) {
                $table->foreignId('user_assigner_id')->nullable()->after('approver_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('request_layout', 'division_assigner_id')) {
                $table->foreignId('division_assigner_id')->nullable()->after('user_assigner_id')->constrained('departments')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('request_layout', 'user_id')) {
            DB::table('request_layout')->whereNotNull('user_id')->update([
                'user_assigner_id' => DB::raw('user_id'),
            ]);

            Schema::table('request_layout', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('request_layout', 'user_id')) {
            Schema::table('request_layout', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('schema')->constrained()->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('request_layout', 'user_assigner_id')) {
            DB::table('request_layout')->whereNotNull('user_assigner_id')->update([
                'user_id' => DB::raw('user_assigner_id'),
            ]);
        }

        Schema::table('request_layout', function (Blueprint $table) {
            if (Schema::hasColumn('request_layout', 'division_assigner_id')) {
                $table->dropForeign(['division_assigner_id']);
                $table->dropColumn('division_assigner_id');
            }
            if (Schema::hasColumn('request_layout', 'user_assigner_id')) {
                $table->dropForeign(['user_assigner_id']);
                $table->dropColumn('user_assigner_id');
            }
            if (Schema::hasColumn('request_layout', 'approver_id')) {
                $table->dropForeign(['approver_id']);
                $table->dropColumn('approver_id');
            }
            if (Schema::hasColumn('request_layout', 'version')) {
                $table->dropColumn('version');
            }
            if (Schema::hasColumn('request_layout', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('request_layout', 'has_header')) {
                $table->dropColumn('has_header');
            }
        });
    }
};
