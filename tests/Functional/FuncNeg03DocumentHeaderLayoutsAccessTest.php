<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('site foreman cannot access boiler chief document header layouts', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $foreman = User::query()->create([
        'surname' => 'Мастер',
        'name' => 'Учатска',
        'patronymic' => 'Тестовый',
        'email' => 'foreman1-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 4,
    ]);

    $response = $this->actingAs($foreman)->get(route('boiler-chief.document-header-layouts.index'));

    $response->assertForbidden();
    $response->assertSee('Раздел доступен только начальнику котельной.', false);
});
