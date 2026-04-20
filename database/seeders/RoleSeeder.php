<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Фиксированные id: 1 — директор, 2 — начальник отдела снабжения, 3 — бухгалтер,
     * 4 — мастер участка, 5 — администратор, 6 — технический директор, 7 — начальник котельной.
     */
    public function run(): void
    {
        foreach ($this->rolesById() as $id => $name) {
            Role::updateOrCreate(
                ['id' => $id],
                ['name' => $name]
            );
        }
    }

    /**
     * @return array<int, string>
     */
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
