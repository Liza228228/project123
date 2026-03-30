<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Role::MAP as $id => $name) {
            Role::updateOrCreate(
                ['id' => $id],
                ['name' => $name]
            );
        }
    }
}
