<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('equipment')) {
            return;
        }

        if (! Schema::hasColumn('equipment', 'value')) {
            Schema::table('equipment', function (Blueprint $table) {
                $table->string('value', 120)->nullable()->after('name');
            });
        }

        if (Schema::hasColumn('equipment', 'size_value')) {
            $rows = DB::table('equipment')
                ->select(['id', 'name', 'size_value'])
                ->whereNotNull('size_value')
                ->get();

            foreach ($rows as $row) {
                $value = trim((string) $row->size_value);
                $name = trim((string) $row->name);
                $normalizedName = $name;
                if ($value !== '') {
                    $suffix = ' '.$value;
                    if (mb_substr($name, -mb_strlen($suffix)) === $suffix) {
                        $candidate = trim((string) mb_substr($name, 0, mb_strlen($name) - mb_strlen($suffix)));
                        if ($candidate !== '') {
                            $normalizedName = $candidate;
                        }
                    }
                }

                DB::table('equipment')
                    ->where('id', (int) $row->id)
                    ->update([
                        'name' => mb_substr($normalizedName, 0, 150),
                        'value' => $value !== '' ? mb_substr($value, 0, 120) : null,
                    ]);
            }

            Schema::table('equipment', function (Blueprint $table) {
                $table->dropColumn('size_value');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('equipment')) {
            return;
        }

        if (! Schema::hasColumn('equipment', 'size_value')) {
            Schema::table('equipment', function (Blueprint $table) {
                $table->string('size_value', 120)->nullable()->after('name');
            });
        }

        if (Schema::hasColumn('equipment', 'value')) {
            DB::table('equipment')
                ->whereNotNull('value')
                ->update(['size_value' => DB::raw('value')]);

            Schema::table('equipment', function (Blueprint $table) {
                $table->dropColumn('value');
            });
        }
    }
};
