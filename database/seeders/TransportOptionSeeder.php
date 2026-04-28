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

            $attrs = ['name' => $name];
            if (Schema::hasColumn('transport_options', 'plate')) {
                $attrs['plate'] = null;
                $attrs['label'] = null;
            }
            TransportOption::create($attrs);
        }

        if (Schema::hasColumn('transport_options', 'plate')) {
            foreach ($this->companyPlatedVehicles() as $row) {
                TransportOption::query()->updateOrCreate(
                    ['plate' => $row['plate']],
                    [
                        'name' => $row['name'],
                        'label' => $row['label'] ?? null,
                    ]
                );
            }
        }
    }

    /**
     * @return list<array{name: string, plate: string, label?: string|null}>
     */
    private function companyPlatedVehicles(): array
    {
        return [
            ['name' => 'Машина', 'plate' => '888', 'label' => 'Своя машина'],
            ['name' => 'Машина', 'plate' => '777', 'label' => 'Своя машина'],
        ];
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
