<?php

// начальные данные для базы
namespace Database\Seeders;

use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Database\Seeder;
class BoilerChiefSubdivisionSeeder extends Seeder
{
    public function run(): void
    {
        $subdivisionNames = SubdivisionSeeder::definitionNames();
        $emails = UserSeeder::BOILER_CHIEF_SEED_EMAILS;

        foreach ($subdivisionNames as $index => $subName) {
            $email = $emails[$index] ?? null;
            if ($email === null) {
                break;
            }

            $chief = User::query()
                ->where('email', $email)
                ->where('role_id', 7)
                ->first();

            if (! $chief) {
                $this->command?->warn("Нет пользователя начальника котельной: {$email}");

                continue;
            }

            $subdivision = Subdivision::query()->where('name', $subName)->first();
            if ($subdivision === null) {
                $this->command?->warn("Нет подразделения «{$subName}».");

                continue;
            }

            $chief->boilerChiefSubdivisions()->sync([(int) $subdivision->id]);
        }

        $extraPairs = [
            ['email' => $emails[27] ?? null, 'subdivision_index' => 0],
            ['email' => $emails[28] ?? null, 'subdivision_index' => 1],
        ];

        foreach ($extraPairs as $pair) {
            $email = $pair['email'];
            if ($email === null) {
                continue;
            }

            $chief = User::query()
                ->where('email', $email)
                ->where('role_id', 7)
                ->first();

            if (! $chief) {
                continue;
            }

            $subName = $subdivisionNames[$pair['subdivision_index']] ?? null;
            if ($subName === null) {
                continue;
            }

            $subdivision = Subdivision::query()->where('name', $subName)->first();
            if ($subdivision === null) {
                continue;
            }

            $chief->boilerChiefSubdivisions()->sync([(int) $subdivision->id]);
        }
    }
}
