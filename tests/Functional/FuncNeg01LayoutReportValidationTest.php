<?php

// функциональный тест
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('layout report is not saved when template and fields are missing', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $chief = User::query()->create([
        'surname' => 'Тест',
        'name' => 'Тест',
        'patronymic' => 'Тест',
        'email' => 'test'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);

    $response = $this->actingAs($chief)->from(route('boiler-chief.layout-applications.create'))->post(
        route('boiler-chief.layout-applications.store'),
        [
            'layout_structure_id' => '',
        ]
    );

    $response->assertRedirect(route('boiler-chief.layout-applications.create'));
    $response->assertSessionHasErrors(['layout_structure_id', 'values']);
});
