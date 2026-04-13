<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        $now = now();
        foreach (
            [
                ['code' => 'pending', 'name' => 'На согласовании'],
                ['code' => 'approved', 'name' => 'Согласована'],
                ['code' => 'rejected', 'name' => 'Не согласована'],
                ['code' => 'partial', 'name' => 'Частично согласована'],
            ] as $row
        ) {
            DB::table('application_statuses')->insert([
                'code' => $row['code'],
                'name' => $row['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $pendingId = (int) DB::table('application_statuses')->where('code', 'pending')->value('id');

        Schema::table('applications', function (Blueprint $table) use ($pendingId) {
            $table->foreignId('application_status_id')
                ->default($pendingId)
                ->after('transport_option_id')
                ->constrained('application_statuses')
                ->restrictOnDelete();
            $table->text('approval_rejection_reason')->nullable()->after('application_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('application_status_id');
            $table->dropColumn('approval_rejection_reason');
        });

        Schema::dropIfExists('application_statuses');
    }
};
