<?php

namespace Database\Seeders;

use App\Models\Subdivision;
use App\Models\User;
use App\Support\AdministrationWarehouse;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Мастера участка (role_id = 4): у подразделения может быть несколько мастеров;
 * у каждого подразделения из {@see SubdivisionSeeder::definitionNames()} (кроме «Администрация») — хотя бы один.
 */
class ForemanSubdivisionSeeder extends Seeder
{
    /**
     * Подразделения, на которые можно назначать мастеров участка.
     *
     * @return list<string>
     */
    public static function assignableSubdivisionNames(): array
    {
        return array_values(array_filter(
                SubdivisionSeeder::definitionNames(),
            fn (string $name): bool => $name !== AdministrationWarehouse::SUBDIVISION_NAME,
        ));
    }

    public function run(): void
    {
        $subdivisionNames = self::assignableSubdivisionNames();
        $foremanEmails = UserSeeder::FOREMAN_SEED_EMAILS;

        $primaryCount = count($subdivisionNames);
        $primaryEmails = array_slice($foremanEmails, 0, $primaryCount);
        $extraEmails = array_slice($foremanEmails, $primaryCount);

        if (count($primaryEmails) < $primaryCount) {
            throw new RuntimeException(
                'UserSeeder::FOREMAN_SEED_EMAILS must contain at least as many emails as foreman-assignable subdivisions.'
            );
        }

        $assignments = [];
        foreach (range(0, $primaryCount - 1) as $i) {
            $assignments[$primaryEmails[$i]] = [$subdivisionNames[$i]];
        }

        $assignments['Kozlov@mail.ru'] = ['Лаборатория технического контроля'];

        if (isset($extraEmails[0])) {
            $assignments[$extraEmails[0]] = [$subdivisionNames[0]];
        }
        if (isset($extraEmails[1])) {
            $assignments[$extraEmails[1]] = [$subdivisionNames[0]];
        }
        if (isset($extraEmails[2])) {
            $assignments[$extraEmails[2]] = [$subdivisionNames[1]];
        }

        foreach ($assignments as $foremanEmail => $names) {
            $foreman = User::query()
                ->where('email', $foremanEmail)
                ->where('role_id', 4)
                ->first();

            if (! $foreman) {
                continue;
            }

            $subdivisionIds = Subdivision::query()
                ->whereIn('name', $names)
                ->pluck('id');

            $foreman->assignedSubdivisions()->sync($subdivisionIds->all());
        }
    }
}
