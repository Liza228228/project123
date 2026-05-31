<?php

// функциональный тест
use App\Models\Subdivision;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DadataAddressService;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('cannot add warehouse with address that already exists in system', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $director = User::query()->create([
        'surname' => 'Директор',
        'name' => 'Склад',
        'patronymic' => 'Тест',
        'email' => 'director-wh-dup-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    $subdivisionA = Subdivision::query()->create(['name' => 'Подразделение А '.uniqid()]);
    $subdivisionB = Subdivision::query()->create(['name' => 'Подразделение Б '.uniqid()]);

    $addressParts = [
        'address_postal_code' => '123456',
        'address_region' => 'г Москва',
        'address_city' => 'г Москва',
        'address_street' => 'ул Тестовая',
        'address_house' => '10',
        'address_block' => null,
        'address_flat' => null,
        'address_fias_id' => 'duplicate-fias-'.uniqid(),
    ];

    Warehouse::query()->create([
        'name' => 'Склад существующий',
        'subdivision_id' => $subdivisionA->id,
        'is_primary' => false,
        ...$addressParts,
    ]);

    $this->mock(DadataAddressService::class, function ($mock) use ($addressParts): void {
        $mock->shouldReceive('clean')
            ->once()
            ->andReturn([
                'postal_code' => $addressParts['address_postal_code'],
                'region_with_type' => $addressParts['address_region'],
                'city_with_type' => $addressParts['address_city'],
                'street_with_type' => $addressParts['address_street'],
                'house' => $addressParts['address_house'],
                'block' => null,
                'flat' => null,
                'fias_id' => $addressParts['address_fias_id'],
            ]);
    });

    $this->actingAs($director)
        ->from(route('foreman-subdivisions.index'))
        ->post(route('foreman-subdivisions.warehouses.store'), [
            'subdivision_id' => $subdivisionB->id,
            'warehouse_name' => 'Склад новый',
            'address' => 'г Москва, ул Тестовая, д 10',
        ])
        ->assertRedirect(route('foreman-subdivisions.index'))
        ->assertSessionHasErrors(['address' => 'Склад с таким адресом уже есть в системе. Укажите другой адрес.']);

    expect(Warehouse::query()->where('subdivision_id', $subdivisionB->id)->count())->toBe(0);
});
