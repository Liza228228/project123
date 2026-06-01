<?php

// функциональный тест
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

test('director and technical director see identical full report generator navigation', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $director = User::query()->create([
        'surname' => 'Директор',
        'name' => 'Меню',
        'patronymic' => 'Отчётов',
        'email' => 'director-nav-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    $technicalDirector = User::query()->create([
        'surname' => 'Техдир',
        'name' => 'Меню',
        'patronymic' => 'Отчётов',
        'email' => 'td-nav-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 6,
    ]);

    $supplyHead = User::query()->create([
        'surname' => 'Снабжение',
        'name' => 'Меню',
        'patronymic' => 'Отчётов',
        'email' => 'supply-nav-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $directorNav = $this->actingAs($director)->get(route('dashboard'))->assertOk()->getContent();
    $tdNav = $this->actingAs($technicalDirector)->get(route('dashboard'))->assertOk()->getContent();

    foreach (['Генератор отчётов', 'Макеты шапок', 'Макеты отчетов (PDF)', 'Отчеты по макетам'] as $label) {
        expect($directorNav)->toContain($label);
        expect($tdNav)->toContain($label);
    }

    $supplyNav = $this->actingAs($supplyHead)->get(route('dashboard'))->assertOk()->getContent();

    expect($supplyNav)->toContain('Заявки');
    expect($supplyNav)->toContain('Оборудование к заказу');
    expect($supplyNav)->toContain('Склады и оборудование');
    expect($supplyNav)->not->toContain('Генератор отчётов');

    expect($tdNav)->toContain('Заявки');
    expect($tdNav)->toContain('Генератор отчётов');
    expect($tdNav)->not->toContain('Оборудование к заказу');
    expect($tdNav)->not->toContain('Склады и оборудование');
});

test('technical director shares application workflow with supply head but not procurement or materials', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $supplyHead = User::query()->create([
        'surname' => 'Снабжение',
        'name' => 'Паритет',
        'patronymic' => 'Тест',
        'email' => 'supply-parity-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $technicalDirector = User::query()->create([
        'surname' => 'Техдир',
        'name' => 'Паритет',
        'patronymic' => 'Тест',
        'email' => 'td-parity-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 6,
    ]);

    foreach ([$supplyHead, $technicalDirector] as $actor) {
        $this->actingAs($actor)->get(route('applications.index'))->assertOk();
        $this->actingAs($actor)->get(route('applications.installation-act.upload'))->assertOk();
    }

    $this->actingAs($supplyHead)->get(route('applications.custom-equipment-to-order'))->assertOk();
    $this->actingAs($supplyHead)->get(route('materials.index'))->assertOk();

    $this->actingAs($technicalDirector)->get(route('applications.custom-equipment-to-order'))->assertForbidden();
    $this->actingAs($technicalDirector)->get(route('materials.index'))->assertForbidden();
    $this->actingAs($technicalDirector)->get(route('materials.overview'))->assertOk();
    $this->actingAs($technicalDirector)->get(route('materials.movements'))->assertOk();
    $this->actingAs($technicalDirector)->get(route('boiler-chief.request-layouts.index'))->assertOk();

    $this->actingAs($supplyHead)->get(route('boiler-chief.request-layouts.index'))->assertForbidden();
});

test('supply head cannot access report generator sections but shares application workflow with director', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $supplyHead = User::query()->create([
        'surname' => 'Снабжение',
        'name' => 'Доступ',
        'patronymic' => 'Тест',
        'email' => 'supply-access-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $this->actingAs($supplyHead)->get(route('boiler-chief.request-layouts.index'))->assertForbidden();
    $this->actingAs($supplyHead)->get(route('boiler-chief.layout-applications.index'))->assertForbidden();
    $this->actingAs($supplyHead)->get(route('boiler-chief.document-header-layouts.index'))->assertForbidden();

    $this->actingAs($supplyHead)->get(route('applications.index'))->assertOk();
    $this->actingAs($supplyHead)->get(route('applications.custom-equipment-to-order'))->assertOk();
    $this->actingAs($supplyHead)->get(route('applications.installation-act.upload'))->assertOk();
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
