<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeliveryStatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('delivery_statuses')->upsert([
            ['id' => 1, 'name' => 'В пути'],
            ['id' => 2, 'name' => 'Доставлено'],
        ], ['id'], ['name']);
    }
}
