<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('site foreman cannot access users administration section', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $foreman = User::query()->create([
        'surname' => 'Мастер',
        'name' => 'Учатка',
        'patronymic' => 'Тест',
        'email' => 'foreman-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 4,
    ]);

    $response = $this->actingAs($foreman)->get('/users');

    $response->assertForbidden();
    $response->assertSee('Доступ разрешён только администраторам.', false);
});
