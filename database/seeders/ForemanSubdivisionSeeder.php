<?php

namespace Database\Seeders;

use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Database\Seeder;

class ForemanSubdivisionSeeder extends Seeder
{
    public function run(): void
    {
        $assignments = [
            'Kozlov@mail.ru' => [
                'Район тепловых сетей Северный',
                'Участок котельных',
            ],
        ];

        $assignerId = User::query()->where('role_id', 1)->value('id');

        foreach ($assignments as $foremanEmail => $subdivisionNames) {
            $foreman = User::query()
                ->where('email', $foremanEmail)
                ->where('role_id', 4)
                ->first();

            if (! $foreman) {
                continue;
            }

            $subdivisionIds = Subdivision::query()
                ->whereIn('name', $subdivisionNames)
                ->pluck('id');

            $syncData = $subdivisionIds
                ->mapWithKeys(fn (int $subdivisionId): array => [
                    $subdivisionId => ['assigned_by_user_id' => $assignerId],
                ])
                ->all();

            $foreman->assignedSubdivisions()->sync($syncData);
        }
    }
}
