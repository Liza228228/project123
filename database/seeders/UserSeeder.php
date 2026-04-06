<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'surname' => 'Иванов',
                'name' => 'Иван',
                'patronymic' => 'Иванович',
                'email' => 'Ivanov@mail.ru',
                'password' => Hash::make('11111111'),
                'role_id' => Role::ID_DIRECTOR,
            ],
            [
                'surname' => 'Петров',
                'name' => 'Пётр',
                'patronymic' => 'Петрович',
                'email' => 'Petrov@mail.ru',
                'password' => Hash::make('11111111'),
                'role_id' => Role::ID_SUPPLY_DEPARTMENT_HEAD,
            ],
            [
                'surname' => 'Сидорова',
                'name' => 'Мария',
                'patronymic' => 'Сергеевна',
                'email' => 'Sidorova@mail.ru',
                'password' => Hash::make('11111111'),
                'role_id' => Role::ID_ACCOUNTANT,
            ],
            [
                'surname' => 'Козлов',
                'name' => 'Алексей',
                'patronymic' => 'Николаевич',
                'email' => 'Kozlov@mail.ru',
                'password' => Hash::make('11111111'),
                'role_id' => Role::ID_SITE_FOREMAN,
            ],
            [
                'surname' => 'Смирнов',
                'name' => 'Дмитрий',
                'patronymic' => 'Александрович',
                'email' => 'Smirnov@mail.ru',
                'password' => Hash::make('11111111'),
                'role_id' => Role::ID_ADMINISTRATOR,
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                $data
            );
        }
    }
}
