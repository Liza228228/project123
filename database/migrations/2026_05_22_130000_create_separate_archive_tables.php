<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->timestamp('archived_at');
            $table->timestamp('admin_archived_at')->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('archived_at');
            $table->index('admin_archived_at');
        });

        Schema::create('subdivision_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subdivision_id')->unique()->constrained('subdivisions')->cascadeOnDelete();
            $table->timestamp('archived_at');
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('archived_at');
        });

        if (Schema::hasColumn('applications', 'archived_at')) {
            DB::table('applications')
                ->whereNotNull('archived_at')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('application_archives')->insert([
                            'application_id' => (int) $row->id,
                            'archived_at' => $row->archived_at,
                            'admin_archived_at' => $row->admin_archived_at ?? null,
                            'archived_by_user_id' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });

            Schema::table('applications', function (Blueprint $table) {
                if (Schema::hasColumn('applications', 'admin_archived_at')) {
                    $table->dropIndex(['admin_archived_at']);
                    $table->dropColumn('admin_archived_at');
                }

                $table->dropIndex(['archived_at']);
                $table->dropColumn('archived_at');
            });
        }

        if (Schema::hasColumn('subdivisions', 'is_active')) {
            DB::table('subdivisions')
                ->where('is_active', false)
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('subdivision_archives')->insert([
                            'subdivision_id' => (int) $row->id,
                            'archived_at' => now(),
                            'archived_by_user_id' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });

            Schema::table('subdivisions', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (! Schema::hasColumn('applications', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('approved_by_user_id');
                $table->index('archived_at');
            }

            if (! Schema::hasColumn('applications', 'admin_archived_at')) {
                $table->timestamp('admin_archived_at')->nullable()->after('archived_at');
                $table->index('admin_archived_at');
            }
        });

        if (Schema::hasTable('application_archives')) {
            DB::table('application_archives')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('applications')
                            ->where('id', (int) $row->application_id)
                            ->update([
                                'archived_at' => $row->archived_at,
                                'admin_archived_at' => $row->admin_archived_at,
                            ]);
                    }
                });
        }

        Schema::table('subdivisions', function (Blueprint $table) {
            if (! Schema::hasColumn('subdivisions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('name');
            }
        });

        if (Schema::hasTable('subdivision_archives')) {
            DB::table('subdivision_archives')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('subdivisions')
                            ->where('id', (int) $row->subdivision_id)
                            ->update(['is_active' => false]);
                    }
                });
        }

        Schema::dropIfExists('subdivision_archives');
        Schema::dropIfExists('application_archives');
    }
};
