<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_change_journal')) {
            Schema::create('application_change_journal', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
                $table->foreignId('application_item_id')->nullable()->constrained('application_items')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('action');
                $table->string('field_key', 64);
                $table->string('reason', 500);
                $table->timestamp('created_at')->useCurrent();

                $table->index(['application_id', 'created_at']);
                $table->index(['application_item_id', 'created_at']);
            });
        }

        if (! Schema::hasColumn('application_items', 'removed_at')) {
            Schema::table('application_items', function (Blueprint $table): void {
                if (Schema::hasColumn('application_items', 'expected_arrival_at')) {
                    $table->timestamp('removed_at')->nullable()->after('expected_arrival_at');
                } else {
                    $table->timestamp('removed_at')->nullable()->after('transport_option_id');
                }
            });
        }

        if (! Schema::hasColumn('application_items', 'removed_by_user_id')) {
            Schema::table('application_items', function (Blueprint $table): void {
                $table->foreignId('removed_by_user_id')
                    ->nullable()
                    ->after('removed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        $this->dropLegacyChangeReasonColumns();
    }

    public function down(): void
    {
        Schema::dropIfExists('application_change_journal');

        if (Schema::hasColumn('application_items', 'removed_by_user_id')) {
            Schema::table('application_items', function (Blueprint $table): void {
                $table->dropForeign(['removed_by_user_id']);
                $table->dropColumn('removed_by_user_id');
            });
        }

        if (Schema::hasColumn('application_items', 'removed_at')) {
            Schema::table('application_items', function (Blueprint $table): void {
                $table->dropColumn('removed_at');
            });
        }
    }

    private function dropLegacyChangeReasonColumns(): void
    {
        if (Schema::hasColumn('applications', 'subdivision_change_reason')
            || Schema::hasColumn('applications', 'desired_delivery_change_reason')
            || Schema::hasColumn('applications', 'new_items_change_reason')) {
            Schema::table('applications', function (Blueprint $table): void {
                $drops = [];
                if (Schema::hasColumn('applications', 'subdivision_change_reason')) {
                    $drops[] = 'subdivision_change_reason';
                }
                if (Schema::hasColumn('applications', 'desired_delivery_change_reason')) {
                    $drops[] = 'desired_delivery_change_reason';
                }
                if (Schema::hasColumn('applications', 'new_items_change_reason')) {
                    $drops[] = 'new_items_change_reason';
                }
                if ($drops !== []) {
                    $table->dropColumn($drops);
                }
            });
        }

        if (Schema::hasColumn('application_items', 'last_content_changed_by_user_id')) {
            Schema::table('application_items', function (Blueprint $table): void {
                $table->dropForeign(['last_content_changed_by_user_id']);
            });
        }

        $itemDrops = array_values(array_filter([
            Schema::hasColumn('application_items', 'field_change_reasons') ? 'field_change_reasons' : null,
            Schema::hasColumn('application_items', 'last_content_change_reason') ? 'last_content_change_reason' : null,
            Schema::hasColumn('application_items', 'last_content_changed_at') ? 'last_content_changed_at' : null,
            Schema::hasColumn('application_items', 'last_content_changed_by_user_id') ? 'last_content_changed_by_user_id' : null,
            Schema::hasColumn('application_items', 'removal_reason') ? 'removal_reason' : null,
        ]));

        if ($itemDrops !== []) {
            Schema::table('application_items', function (Blueprint $table) use ($itemDrops): void {
                $table->dropColumn($itemDrops);
            });
        }
    }
};
