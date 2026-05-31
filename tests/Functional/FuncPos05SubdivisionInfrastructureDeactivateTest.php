<?php

// функциональный тест
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\Subdivision;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\ForemanApplicationReassignment;
use App\Support\SubdivisionInfrastructureDeactivation;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('only administrator can deactivate subdivisions', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $subdivision = Subdivision::query()->create(['name' => 'Запрет деактивации']);
    $director = User::query()->create([
        'surname' => 'Дир',
        'name' => 'Тест',
        'patronymic' => '',
        'email' => 'director-deact-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    $this->actingAs($director)
        ->getJson(route('foreman-subdivisions.subdivisions.deactivate-preview', $subdivision))
        ->assertForbidden();

    $this->actingAs($director)
        ->post(route('foreman-subdivisions.subdivisions.deactivate', $subdivision))
        ->assertForbidden();
});

test('deactivate preview lists all assigned staff with detach option', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $active = Subdivision::query()->create(['name' => 'Активное для снятия']);
    $other = Subdivision::query()->create(['name' => 'Другое активное']);
    $toDeactivate = Subdivision::query()->create(['name' => 'Отключаемое']);

    $chief = User::query()->create([
        'surname' => 'Нач',
        'name' => 'Кот',
        'patronymic' => '',
        'email' => 'chief-deact-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$toDeactivate->id, $other->id]);

    $foreman = createForemanForDeactivateTest($toDeactivate, 'Только здесь');

    $admin = createAdministratorForDeactivateTest();

    $this->actingAs($admin)
        ->getJson(route('foreman-subdivisions.subdivisions.deactivate-preview', $toDeactivate))
        ->assertOk()
        ->assertJsonPath('hard_block', null)
        ->assertJsonPath('requires_staff_actions', true)
        ->assertJsonCount(1, 'boiler_chiefs')
        ->assertJsonPath('boiler_chiefs.0.has_other_subdivisions', true)
        ->assertJsonCount(1, 'foremen')
        ->assertJsonPath('foremen.0.has_other_subdivisions', false);
});

test('administrator can deactivate subdivision with detach only for staff', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $other = Subdivision::query()->create(['name' => 'Остаётся']);
    $toDeactivate = Subdivision::query()->create(['name' => 'Станет недоступным']);

    $chief = User::query()->create([
        'surname' => 'Нач',
        'name' => 'Два',
        'patronymic' => '',
        'email' => 'chief-deact2-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$toDeactivate->id, $other->id]);

    $foreman = createForemanForDeactivateTest($toDeactivate, 'Один');

    $admin = createAdministratorForDeactivateTest();

    $this->actingAs($admin)
        ->post(route('foreman-subdivisions.subdivisions.deactivate', $toDeactivate), [
            'chief_subdivisions' => [(string) $chief->id => SubdivisionInfrastructureDeactivation::DETACH_ONLY_VALUE],
            'foreman_subdivisions' => [(string) $foreman->id => SubdivisionInfrastructureDeactivation::DETACH_ONLY_VALUE],
        ])
        ->assertRedirect(route('foreman-subdivisions.index'))
        ->assertSessionHas('status');

    $toDeactivate->refresh();
    expect($toDeactivate->fresh()->isArchived())->toBeTrue();
    expect($chief->fresh()->boilerChiefSubdivisions()->pluck('subdivisions.id')->all())
        ->toBe([(int) $other->id]);
    expect($foreman->fresh()->assignedSubdivisions()->pluck('subdivisions.id')->all())->toBe([]);
});

test('deactivating subdivision keeps applications in that subdivision when foreman is detached', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $other = Subdivision::query()->create(['name' => 'Другое для мастера']);
    $subdivision = Subdivision::query()->create(['name' => 'С заявками деакт']);
    $warehouse = Warehouse::query()->create([
        'name' => 'Склад в отключаемом',
        'subdivision_id' => $subdivision->id,
    ]);
    $foreman = createForemanForDeactivateTest($subdivision, 'Мастер');
    $foreman->assignedSubdivisions()->sync([$subdivision->id, $other->id]);

    $application = Application::query()->create([
        'subdivision_id' => $subdivision->id,
        'user_id' => $foreman->id,
        'responsible_user_id' => $foreman->id,
        'desired_delivery_date' => now()->addDays(2),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'approved_by_user_id' => $foreman->id,
    ]);

    $admin = createAdministratorForDeactivateTest();

    $this->actingAs($admin)
        ->post(route('foreman-subdivisions.subdivisions.deactivate', $subdivision), [
            'foreman_subdivisions' => [(string) $foreman->id => SubdivisionInfrastructureDeactivation::DETACH_ONLY_VALUE],
        ])
        ->assertRedirect(route('foreman-subdivisions.index'))
        ->assertSessionHas('status');

    $application->refresh();
    expect($subdivision->fresh()->isArchived())->toBeTrue();
    expect((int) $application->subdivision_id)->toBe((int) $subdivision->id);
    expect($foreman->fresh()->assignedSubdivisions()->pluck('subdivisions.id')->all())
        ->toBe([(int) $other->id]);
    expect(Warehouse::query()->whereKey($warehouse->id)->exists())->toBeTrue();
});

function createAdministratorForDeactivateTest(): User
{
    return User::query()->create([
        'surname' => 'Админ',
        'name' => 'Деактивация',
        'patronymic' => 'Тест',
        'email' => 'admin-deact-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ADMINISTRATOR_ROLE_ID,
    ]);
}

function createForemanForDeactivateTest(Subdivision $subdivision, string $label): User
{
    $foreman = User::query()->create([
        'surname' => $label,
        'name' => 'Мастер',
        'patronymic' => 'Тест',
        'email' => 'foreman-deact-'.$label.'-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => ForemanApplicationReassignment::FOREMAN_ROLE_ID,
    ]);
    $foreman->assignedSubdivisions()->sync([$subdivision->id]);

    return $foreman;
}
