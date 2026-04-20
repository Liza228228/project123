<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_installation_act_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('path', 512);
            $table->timestamps();
        });

        if (Schema::hasColumn('applications', 'installation_act_photo_paths')) {
            $rows = DB::table('applications')
                ->select('id', 'installation_act_photo_paths')
                ->whereNotNull('installation_act_photo_paths')
                ->get();

            foreach ($rows as $row) {
                $decoded = json_decode((string) $row->installation_act_photo_paths, true);
                if (! is_array($decoded)) {
                    continue;
                }
                foreach ($decoded as $path) {
                    if (is_string($path) && trim($path) !== '') {
                        DB::table('application_installation_act_photos')->insert([
                            'application_id' => $row->id,
                            'path' => $path,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            Schema::table('applications', function (Blueprint $table) {
                $table->dropColumn('installation_act_photo_paths');
            });
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->json('installation_act_photo_paths')->nullable()->after('installation_act_path');
        });

        $photos = DB::table('application_installation_act_photos')
            ->orderBy('application_id')
            ->orderBy('id')
            ->get()
            ->groupBy('application_id');

        foreach ($photos as $applicationId => $group) {
            $paths = $group->pluck('path')->values()->all();
            DB::table('applications')->where('id', $applicationId)->update([
                'installation_act_photo_paths' => json_encode($paths),
            ]);
        }

        Schema::dropIfExists('application_installation_act_photos');
    }
};
