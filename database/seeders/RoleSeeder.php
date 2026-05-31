<?php

// начальные данные для базы
namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rolesById() as $id => $name) {
            Role::updateOrCreate(
                ['id' => $id],
                ['name' => $name]
            );
        }
    }
    private function rolesById(): array
    {
        return [
            1 => 'Директор',
            2 => 'Начальник отдела снабжения',
            3 => 'Бухгалтер',
            4 => 'Мастер участка',
            5 => 'Администратор',
            6 => 'Технический директор',
            7 => 'Начальник котельной',
        ];
    }
}
