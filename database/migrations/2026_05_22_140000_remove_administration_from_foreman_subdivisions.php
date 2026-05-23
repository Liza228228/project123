<?php

use App\Support\AdministrationWarehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $adminSubdivisionId = AdministrationWarehouse::subdivisionId();
        if ($adminSubdivisionId === null) {
            return;
        }

        DB::table('foreman_subdivision_user')
            ->where('subdivision_id', $adminSubdivisionId)
            ->delete();
    }

    public function down(): void
    {
        // Назначения мастеров на «Администрацию» не восстанавливаются.
    }
};
