<?php

use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('technical director can view subdivisions but cannot create them or warehouses', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $technicalDirector = User::query()->create([
        'surname' => 'Тех',
        'name' => 'Директор',
        'patronymic' => 'Тест',
        'email' => 'td-infra-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 6,
    ]);

    $this->actingAs($technicalDirector)
        ->get(route('foreman-subdivisions.index'))
        ->assertOk()
        ->assertDontSee('Добавить подразделение', false)
        ->assertDontSee('Добавить склад', false);

    $this->actingAs($technicalDirector)
        ->post(route('foreman-subdivisions.subdivisions.store'), [
            'subdivision_name' => 'Новое подразделение ТД',
        ])
        ->assertForbidden();

    $subdivision = Subdivision::query()->create(['name' => 'Склад ТД запрет']);

    $this->actingAs($technicalDirector)
        ->post(route('foreman-subdivisions.warehouses.store'), [
            'subdivision_id' => $subdivision->id,
            'warehouse_name' => 'Склад ТД',
            'address' => 'г. Тест, ул. Примерная, 1',
        ])
        ->assertForbidden();
});
