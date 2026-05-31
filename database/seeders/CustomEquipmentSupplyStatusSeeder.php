<?php

// начальные данные для базы
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomEquipmentSupplyStatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('custom_equipment_supply_statuses')->upsert([
            ['id' => 1, 'name' => 'На согласовании'],
            ['id' => 2, 'name' => 'Принято по заявке'],
            ['id' => 3, 'name' => 'Заказано'],
            ['id' => 4, 'name' => 'В пути'],
            ['id' => 5, 'name' => 'На складе'],
        ], ['id'], ['name']);
    }
}
