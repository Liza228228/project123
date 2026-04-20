<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DepartmentSeeder extends Seeder
{
    /**
     * Справочник подразделений для связи request_layout.division_assigner_id.
     * По умолчанию копирует названия из subdivisions, если они уже есть в БД.
     */
    public function run(): void
    {
        if (! Schema::hasTable('subdivisions')) {
            return;
        }

        $names = DB::table('subdivisions')->orderBy('name')->pluck('name')->unique()->filter();
        foreach ($names as $name) {
            Department::query()->firstOrCreate(['name' => (string) $name]);
        }
    }
}
