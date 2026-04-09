<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\TransportOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class TransportOptionSeeder extends Seeder
{

    public function run(): void
    {
        if (! Schema::hasTable('transport_options')) {
            return;
        }

        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'transport_option_id')) {
            Application::query()->update(['transport_option_id' => null]);
        }
        TransportOption::query()->delete();

        foreach ($this->transportTypeNames() as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            TransportOption::create([
                'name' => $name,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function transportTypeNames(): array
    {
        return [
            'Машина',
            'Маршрутка',
            'Грузовик',
            'Автобус',
            'Прицеп / фура',
            'Спецтехника',
            'Самовывоз',
        ];
    }
}
