<?php

// функциональный тест
use App\Models\Application;
use App\Models\ApplicationArchive;
use App\Models\ApplicationStatus;
use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('administrator can access applications index and see all applications', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл админ');

    $draft = Application::query()->create([
        'subdivision_id' => $ctx['subdivision']->id,
        'user_id' => $ctx['foreman']->id,
        'responsible_user_id' => $ctx['foreman']->id,
        'desired_delivery_date' => now()->addDays(3),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING),
    ]);

    $admin = User::query()->create([
        'surname' => 'Админ',
        'name' => 'Заявки',
        'patronymic' => 'Тест',
        'email' => 'admin-apps-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ADMINISTRATOR_ROLE_ID,
    ]);

    $this->actingAs($admin)
        ->get(route('applications.index'))
        ->assertOk()
        ->assertSee('№ '.$draft->id, false);

    $this->actingAs($admin)
        ->get(route('applications.show', $draft))
        ->assertOk()
        ->assertSee('Режим просмотра', false);
});

test('administrator cannot create applications', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $admin = User::query()->create([
        'surname' => 'Админ',
        'name' => 'Создать',
        'patronymic' => 'Нет',
        'email' => 'admin-no-create-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ADMINISTRATOR_ROLE_ID,
    ]);

    $this->actingAs($admin)
        ->get(route('applications.create'))
        ->assertForbidden();
});

test('administrator can archive and restore any application', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $subdivision = Subdivision::query()->create(['name' => 'Админ архив']);

    $foreman = User::query()->create([
        'surname' => 'Мастер',
        'name' => 'Архив',
        'patronymic' => 'Тест',
        'email' => 'foreman-arch-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 4,
    ]);
    $foreman->assignedSubdivisions()->sync([$subdivision->id]);

    $application = Application::query()->create([
        'subdivision_id' => $subdivision->id,
        'user_id' => $foreman->id,
        'responsible_user_id' => $foreman->id,
        'desired_delivery_date' => now()->addDays(2),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'approved_by_user_id' => $foreman->id,
    ]);

    $admin = User::query()->create([
        'surname' => 'Админ',
        'name' => 'Архив',
        'patronymic' => 'Тест',
        'email' => 'admin-arch-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ADMINISTRATOR_ROLE_ID,
    ]);

    $this->actingAs($admin)
        ->from(route('applications.index'))
        ->post(route('applications.admin-archive', $application))
        ->assertRedirect(route('applications.index'));

    $application->refresh();
    expect($application->archived_at)->not->toBeNull();
    expect($application->admin_archived_at)->not->toBeNull();

    $this->actingAs($foreman)
        ->get(route('applications.archive'))
        ->assertRedirect();

    $this->actingAs($foreman)
        ->get(route('applications.show', $application))
        ->assertOk()
        ->assertSee('Заявка в архиве', false);

    $this->actingAs($admin)
        ->post(route('applications.admin-unarchive', $application))
        ->assertRedirect();

    $application->refresh();
    expect($application->archived_at)->toBeNull();
    expect($application->admin_archived_at)->toBeNull();
});

test('administrator cannot restore application archived as completed', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл завершён');

    $application = Application::query()->create([
        'subdivision_id' => $ctx['subdivision']->id,
        'user_id' => $ctx['foreman']->id,
        'responsible_user_id' => $ctx['foreman']->id,
        'desired_delivery_date' => now()->addDays(2),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_COMPLETED),
        'approved_by_user_id' => $ctx['foreman']->id,
    ]);
    ApplicationArchive::query()->create([
        'application_id' => $application->id,
        'archived_at' => now(),
    ]);

    $admin = User::query()->create([
        'surname' => 'Админ',
        'name' => 'Нет',
        'patronymic' => 'Восстанов',
        'email' => 'admin-no-restore-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ADMINISTRATOR_ROLE_ID,
    ]);

    $this->actingAs($admin)
        ->post(route('applications.admin-unarchive', $application))
        ->assertRedirect();

    expect($application->fresh()->archived_at)->not->toBeNull();
});

test('site foreman cannot archive or restore application', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл мастер архив');

    $application = Application::query()->create([
        'subdivision_id' => $ctx['subdivision']->id,
        'user_id' => $ctx['foreman']->id,
        'responsible_user_id' => $ctx['foreman']->id,
        'desired_delivery_date' => now()->addDays(2),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'approved_by_user_id' => $ctx['foreman']->id,
    ]);

    $this->actingAs($ctx['foreman'])
        ->get(route('applications.show', $application))
        ->assertOk()
        ->assertDontSee('В архив', false);

    $this->actingAs($ctx['foreman'])
        ->from(route('applications.show', $application))
        ->post(route('applications.admin-archive', $application))
        ->assertForbidden();

    expect($application->fresh()->admin_archived_at)->toBeNull();

    $application->adminForceArchive($ctx['foreman']->id);

    $this->actingAs($ctx['foreman'])
        ->post(route('applications.admin-unarchive', $application))
        ->assertForbidden();

    expect($application->fresh()->admin_archived_at)->not->toBeNull();
});
