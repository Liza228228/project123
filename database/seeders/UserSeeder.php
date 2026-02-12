<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * По одному пользователю на каждую роль. Пароль у всех: password
     */
    public function run(): void
    {
        $users = [
            [
                'surname' => 'Иванов',
                'name' => 'Иван',
                'patronymic' => 'Иванович',
                'email' => '5@5.5',
                'password' => Hash::make('11111111'),
                'role' => User::ROLE_DIRECTOR,
            ],
            [
                'surname' => 'Петров',
                'name' => 'Пётр',
                'patronymic' => 'Петрович',
                'email' => '4@4.4',
                'password' => Hash::make('11111111'),
                'role' => User::ROLE_SUPPLY_DEPARTMENT_HEAD,
            ],
            [
                'surname' => 'Сидорова',
                'name' => 'Мария',
                'patronymic' => 'Сергеевна',
                'email' => '3@3.3',
                'password' => Hash::make('11111111'),
                'role' => User::ROLE_ACCOUNTANT,
            ],
            [
                'surname' => 'Козлов',
                'name' => 'Алексей',
                'patronymic' => 'Николаевич',
                'email' => '2@2.2',
                'password' => Hash::make('11111111'),
                'role' => User::ROLE_SITE_FOREMAN,
            ],
            [
                'surname' => 'Смирнов',
                'name' => 'Дмитрий',
                'patronymic' => 'Александрович',
                'email' => '1@1.1',
                'password' => Hash::make('11111111'),
                'role' => User::ROLE_ADMINISTRATOR,
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
