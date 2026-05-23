<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('applications')) {
            return;
        }

        Schema::table('applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('applications', 'commercial_offer_chief_is_checked')) {
                $table->boolean('commercial_offer_chief_is_checked')->nullable()->after('commercial_offer');
            }
            if (! Schema::hasColumn('applications', 'commercial_offer_chief_reason_not_selected')) {
                $table->string('commercial_offer_chief_reason_not_selected', 500)->nullable()->after('commercial_offer_chief_is_checked');
            }
            if (! Schema::hasColumn('applications', 'commercial_offer_management_is_checked')) {
                $table->boolean('commercial_offer_management_is_checked')->nullable()->after('commercial_offer_chief_reason_not_selected');
            }
            if (! Schema::hasColumn('applications', 'commercial_offer_management_reason_not_selected')) {
                $table->string('commercial_offer_management_reason_not_selected', 500)->nullable()->after('commercial_offer_management_is_checked');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('applications')) {
            return;
        }

        Schema::table('applications', function (Blueprint $table): void {
            foreach ([
                'commercial_offer_management_reason_not_selected',
                'commercial_offer_management_is_checked',
                'commercial_offer_chief_reason_not_selected',
                'commercial_offer_chief_is_checked',
            ] as $column) {
                if (Schema::hasColumn('applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
