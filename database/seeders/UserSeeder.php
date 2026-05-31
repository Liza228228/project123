<?php

// начальные данные для базы
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public const BOILER_CHIEF_SEED_EMAILS = [
        'AntonovSV@mail.ru',
        'BelovDO@mail.ru',
        'VeselovIE@mail.ru',
        'GrishinAK@mail.ru',
        'EgorovMP@mail.ru',
        'ZuevAV@mail.ru',
        'KazakovRN@mail.ru',
        'KuzminTE@mail.ru',
        'LebedevAS@mail.ru',
        'MikhailovGO@mail.ru',
        'NoskovPV@mail.ru',
        'OrlovDV@mail.ru',
        'PonomarevIS@mail.ru',
        'RogovVN@mail.ru',
        'SavchenkoEM@mail.ru',
        'TikhonovAY@mail.ru',
        'UvarovDK@mail.ru',
        'FedorovSB@mail.ru',
        'KharitonovML@mail.ru',
        'ChesnokovVG@mail.ru',
        'ShubinOT@mail.ru',
        'YakovlevDE@mail.ru',
        'YurievAN@mail.ru',
        'YartsevVP@mail.ru',
        'YashinGK@mail.ru',
        'EreminAL@mail.ru',
        'ZhukovKV@mail.ru',
        'GromovAP@mail.ru',
        'DorokhovIS@mail.ru',
    ];
    public const FOREMAN_SEED_EMAILS = [
        'Kozlov@mail.ru',
        'SokolovIM@mail.ru',
        'NikitinPV@mail.ru',
        'MorozovDA@mail.ru',
        'PavlovSI@mail.ru',
        'SemenovIP@mail.ru',
        'GolubevAV@mail.ru',
        'VinogradovMO@mail.ru',
        'BogdanovRS@mail.ru',
        'VorobyovKD@mail.ru',
        'FrolovPA@mail.ru',
        'MedvedevEN@mail.ru',
        'SorokinVI@mail.ru',
        'MelnikovAS@mail.ru',
        'NovikovSP@mail.ru',
        'KrylovDY@mail.ru',
        'SolovyovAV@mail.ru',
        'TerentievOR@mail.ru',
        'ZhdanovNM@mail.ru',
        'BasovIK@mail.ru',
        'RyabovKV@mail.ru',
        'PotapovGA@mail.ru',
        'LukyanovAS@mail.ru',
        'KoshelevDB@mail.ru',
        'AkimovSP@mail.ru',
        'BelousovVA@mail.ru',
        'GusevPD@mail.ru',
        'LavrovIM@mail.ru',
        'DenisovMA@mail.ru',
        'KondratievAS@mail.ru',
    ];

    public function run(): void
    {
        $password = Hash::make('11111111');

        $users = [
            [
                'surname' => 'Иванов',
                'name' => 'Иван',
                'patronymic' => 'Иванович',
                'email' => 'Ivanov@mail.ru',
                'password' => $password,
                'role_id' => 1,
            ],
            [
                'surname' => 'Петров',
                'name' => 'Пётр',
                'patronymic' => 'Петрович',
                'email' => 'Petrov@mail.ru',
                'password' => $password,
                'role_id' => 2,
            ],
            [
                'surname' => 'Сидорова',
                'name' => 'Мария',
                'patronymic' => 'Сергеевна',
                'email' => 'Sidorova@mail.ru',
                'password' => $password,
                'role_id' => 3,
            ],
            [
                'surname' => 'Смирнов',
                'name' => 'Дмитрий',
                'patronymic' => 'Александрович',
                'email' => 'Smirnov@mail.ru',
                'password' => $password,
                'role_id' => 5,
            ],
            [
                'surname' => 'Волков',
                'name' => 'Сергей',
                'patronymic' => 'Викторович',
                'email' => 'Volkov@mail.ru',
                'password' => $password,
                'role_id' => 6,
            ],
        ];

        foreach ($this->foremanUsers($password) as $foreman) {
            $users[] = $foreman;
        }

        foreach ($this->boilerChiefUsers($password) as $chief) {
            $users[] = $chief;
        }

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                $data
            );
        }
    }
    private function boilerChiefUsers(string $password): array
    {
        $rows = [
            ['Антонов', 'Сергей', 'Владимирович', 'AntonovSV@mail.ru'],
            ['Белов', 'Дмитрий', 'Олегович', 'BelovDO@mail.ru'],
            ['Веселов', 'Игорь', 'Евгеньевич', 'VeselovIE@mail.ru'],
            ['Гришин', 'Алексей', 'Константинович', 'GrishinAK@mail.ru'],
            ['Егоров', 'Михаил', 'Павлович', 'EgorovMP@mail.ru'],
            ['Зуев', 'Андрей', 'Викторович', 'ZuevAV@mail.ru'],
            ['Казаков', 'Роман', 'Николаевич', 'KazakovRN@mail.ru'],
            ['Кузьмин', 'Тимофей', 'Егорович', 'KuzminTE@mail.ru'],
            ['Лебедев', 'Артём', 'Сергеевич', 'LebedevAS@mail.ru'],
            ['Михайлов', 'Геннадий', 'Олегович', 'MikhailovGO@mail.ru'],
            ['Носков', 'Павел', 'Владимирович', 'NoskovPV@mail.ru'],
            ['Орлов', 'Денис', 'Вячеславович', 'OrlovDV@mail.ru'],
            ['Пономарёв', 'Илья', 'Станиславович', 'PonomarevIS@mail.ru'],
            ['Рогов', 'Вячеслав', 'Никитич', 'RogovVN@mail.ru'],
            ['Савченко', 'Евгений', 'Максимович', 'SavchenkoEM@mail.ru'],
            ['Тихонов', 'Александр', 'Юрьевич', 'TikhonovAY@mail.ru'],
            ['Уваров', 'Дмитрий', 'Кириллович', 'UvarovDK@mail.ru'],
            ['Фёдоров', 'Степан', 'Борисович', 'FedorovSB@mail.ru'],
            ['Харитонов', 'Максим', 'Леонидович', 'KharitonovML@mail.ru'],
            ['Чесноков', 'Владимир', 'Григорьевич', 'ChesnokovVG@mail.ru'],
            ['Шубин', 'Олег', 'Тимофеевич', 'ShubinOT@mail.ru'],
            ['Яковлев', 'Дмитрий', 'Евгеньевич', 'YakovlevDE@mail.ru'],
            ['Юрьев', 'Андрей', 'Николаевич', 'YurievAN@mail.ru'],
            ['Ярцев', 'Владислав', 'Павлович', 'YartsevVP@mail.ru'],
            ['Яшин', 'Григорий', 'Константинович', 'YashinGK@mail.ru'],
            ['Ерёмин', 'Алексей', 'Львович', 'EreminAL@mail.ru'],
            ['Жуков', 'Кирилл', 'Вадимович', 'ZhukovKV@mail.ru'],
            ['Громов', 'Андрей', 'Платонович', 'GromovAP@mail.ru'],
            ['Дорохов', 'Игорь', 'Семёнович', 'DorokhovIS@mail.ru'],
        ];

        $out = [];
        foreach ($rows as [$surname, $name, $patronymic, $email]) {
            $out[] = [
                'surname' => $surname,
                'name' => $name,
                'patronymic' => $patronymic,
                'email' => $email,
                'password' => $password,
                'role_id' => 7,
            ];
        }

        return $out;
    }
    private function foremanUsers(string $password): array
    {
        $rows = [
            ['Козлов', 'Алексей', 'Николаевич', 'Kozlov@mail.ru'],
            ['Соколов', 'Игорь', 'Михайлович', 'SokolovIM@mail.ru'],
            ['Никитин', 'Павел', 'Викторович', 'NikitinPV@mail.ru'],
            ['Морозов', 'Денис', 'Артёмович', 'MorozovDA@mail.ru'],
            ['Павлов', 'Сергей', 'Ильич', 'PavlovSI@mail.ru'],
            ['Семёнов', 'Иван', 'Петрович', 'SemenovIP@mail.ru'],
            ['Голубев', 'Андрей', 'Владимирович', 'GolubevAV@mail.ru'],
            ['Виноградов', 'Максим', 'Олегович', 'VinogradovMO@mail.ru'],
            ['Богданов', 'Роман', 'Сергеевич', 'BogdanovRS@mail.ru'],
            ['Воробьёв', 'Кирилл', 'Дмитриевич', 'VorobyovKD@mail.ru'],
            ['Фролов', 'Пётр', 'Александрович', 'FrolovPA@mail.ru'],
            ['Медведев', 'Евгений', 'Николаевич', 'MedvedevEN@mail.ru'],
            ['Сорокин', 'Владислав', 'Игоревич', 'SorokinVI@mail.ru'],
            ['Мельников', 'Антон', 'Сергеевич', 'MelnikovAS@mail.ru'],
            ['Новиков', 'Станислав', 'Павлович', 'NovikovSP@mail.ru'],
            ['Крылов', 'Дмитрий', 'Юрьевич', 'KrylovDY@mail.ru'],
            ['Соловьёв', 'Арсений', 'Викторович', 'SolovyovAV@mail.ru'],
            ['Терентьев', 'Олег', 'Романович', 'TerentievOR@mail.ru'],
            ['Жданов', 'Никита', 'Максимович', 'ZhdanovNM@mail.ru'],
            ['Басов', 'Илья', 'Константинович', 'BasovIK@mail.ru'],
            ['Рябов', 'Константин', 'Владимирович', 'RyabovKV@mail.ru'],
            ['Потапов', 'Георгий', 'Александрович', 'PotapovGA@mail.ru'],
            ['Лукьянов', 'Алексей', 'Степанович', 'LukyanovAS@mail.ru'],
            ['Кошелев', 'Дмитрий', 'Борисович', 'KoshelevDB@mail.ru'],
            ['Акимов', 'Сергей', 'Павлович', 'AkimovSP@mail.ru'],
            ['Белоусов', 'Виктор', 'Анатольевич', 'BelousovVA@mail.ru'],
            ['Гусев', 'Павел', 'Дмитриевич', 'GusevPD@mail.ru'],
            ['Лавров', 'Игорь', 'Михайлович', 'LavrovIM@mail.ru'],
            ['Денисов', 'Михаил', 'Андреевич', 'DenisovMA@mail.ru'],
            ['Кондратьев', 'Андрей', 'Сергеевич', 'KondratievAS@mail.ru'],
        ];

        $out = [];
        foreach ($rows as [$surname, $name, $patronymic, $email]) {
            $out[] = [
                'surname' => $surname,
                'name' => $name,
                'patronymic' => $patronymic,
                'email' => $email,
                'password' => $password,
                'role_id' => 4,
            ];
        }

        return $out;
    }
}
