<?php

namespace Database\Seeders;

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
                'role_id' => 1,
            ],
            [
                'surname' => 'Петров',
                'name' => 'Пётр',
                'patronymic' => 'Петрович',
                'email' => 'Petrov@mail.ru',
                'password' => Hash::make('11111111'),
                'role_id' => 2,
            ],
            [
                'surname' => 'Сидорова',
                'name' => 'Мария',
                'patronymic' => 'Сергеевна',
                'email' => 'Sidorova@mail.ru',
                'password' => Hash::make('11111111'),
                'role_id' => 3,
            ],
            [
                'surname' => 'Козлов',
                'name' => 'Алексей',
                'patronymic' => 'Николаевич',
                'email' => 'Kozlov@mail.ru',
                'password' => Hash::make('11111111'),
                'role_id' => 4,
            ],
            [
                'surname' => 'Смирнов',
                'name' => 'Дмитрий',
                'patronymic' => 'Александрович',
                'email' => 'Smirnov@mail.ru',
                'password' => Hash::make('11111111'),
                'role_id' => 5,
            ],
            [
                'surname' => 'Волков',
                'name' => 'Сергей',
                'patronymic' => 'Викторович',
                'email' => 'Volkov@mail.ru',
                'password' => Hash::make('11111111'),
                'role_id' => 6,
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
