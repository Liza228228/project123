<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('site foreman cannot access document header layouts', function (): void {
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
    $response->assertSee('Раздел доступен только директору, техническому директору и администратору.', false);
});

test('site foreman can open report layout catalog for filling', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $foreman = User::query()->create([
        'surname' => 'Мастер',
        'name' => 'Каталог',
        'patronymic' => 'Тест',
        'email' => 'foreman-catalog-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 4,
    ]);

    $this->actingAs($foreman)->get(route('boiler-chief.request-layouts.index'))->assertOk();
});

test('director can open layout applications for report fill', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $director = User::query()->create([
        'surname' => 'Директор',
        'name' => 'Отчёт',
        'patronymic' => 'Тест',
        'email' => 'director-layout-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    $this->actingAs($director)->get(route('boiler-chief.layout-applications.create'))->assertOk();
});

test('boiler chief can open report layout catalog but not template tools', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $chief = User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Котельной',
        'patronymic' => 'Тест',
        'email' => 'chief-layout-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);

    $this->actingAs($chief)->get(route('boiler-chief.request-layouts.index'))->assertOk();

    $this->actingAs($chief)->get(route('boiler-chief.document-header-layouts.index'))
        ->assertForbidden()
        ->assertSee('Раздел доступен только директору, техническому директору и администратору.', false);

    $this->actingAs($chief)->get(route('boiler-chief.request-layouts.create'))
        ->assertForbidden()
        ->assertSee('Раздел доступен только директору, техническому директору и администратору.', false);
});

test('administrator can access report layout designer tools like technical director', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $admin = User::query()->create([
        'surname' => 'Администратор',
        'name' => 'Отчёты',
        'patronymic' => 'Тест',
        'email' => 'admin-layout-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ADMINISTRATOR_ROLE_ID,
    ]);

    $this->actingAs($admin)->get(route('boiler-chief.document-header-layouts.index'))->assertOk();
    $this->actingAs($admin)->get(route('boiler-chief.request-layouts.create'))->assertOk();
    $this->actingAs($admin)->get(route('boiler-chief.layout-applications.index'))->assertOk();
});
