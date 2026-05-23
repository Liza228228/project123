<?php

use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\Subdivision;
use App\Models\User;
use App\Support\ForemanApplicationReassignment;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('admin block preview requires reassignment for foreman with active applications', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $subdivision = Subdivision::query()->create(['name' => 'Блокировка тест']);

    $foremanA = createForemanForSubdivision($subdivision, 'Альфа');
    $foremanB = createForemanForSubdivision($subdivision, 'Бета');

    $application = Application::query()->create([
        'subdivision_id' => $subdivision->id,
        'user_id' => $foremanA->id,
        'responsible_user_id' => $foremanA->id,
        'desired_delivery_date' => now()->addDays(4),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'approved_by_user_id' => $foremanA->id,
    ]);

    $admin = createAdministrator();

    $response = $this->actingAs($admin)->getJson(route('users.block.preview', $foremanA));

    $response->assertOk()
        ->assertJsonPath('requires_reassignment', true)
        ->assertJsonCount(1, 'applications')
        ->assertJsonPath('applications.0.id', $application->id)
        ->assertJsonPath('applications.0.can_reassign', true);

    $foremenIds = collect($response->json('applications.0.foremen'))->pluck('id')->all();
    expect($foremenIds)->toBe([(int) $foremanB->id]);
});

test('admin cannot block foreman until applications reassigned to colleague in same subdivision', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $subdivision = Subdivision::query()->create(['name' => 'Блокировка валидация']);
    $foremanA = createForemanForSubdivision($subdivision, 'Гамма');
    $foremanB = createForemanForSubdivision($subdivision, 'Дельта');

    $application = Application::query()->create([
        'subdivision_id' => $subdivision->id,
        'user_id' => $foremanA->id,
        'responsible_user_id' => $foremanA->id,
        'desired_delivery_date' => now()->addDays(2),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'approved_by_user_id' => $foremanA->id,
    ]);

    $admin = createAdministrator();

    $this->actingAs($admin)
        ->post(route('users.block', $foremanA), ['reassignments' => []])
        ->assertRedirect(route('users.index'))
        ->assertSessionHasErrors();

    expect($foremanA->fresh()->is_blocked)->toBeFalse();
    expect((int) $application->fresh()->user_id)->toBe((int) $foremanA->id);

    $this->actingAs($admin)
        ->post(route('users.block', $foremanA), [
            'reassignments' => [(string) $application->id => (string) $foremanB->id],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('status');

    expect($foremanA->fresh()->is_blocked)->toBeTrue();
    $application->refresh();
    expect((int) $application->user_id)->toBe((int) $foremanB->id);
    expect((int) $application->responsible_user_id)->toBe((int) $foremanB->id);
});

test('admin cannot reassign foreman application to master from another subdivision', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $subdivisionA = Subdivision::query()->create(['name' => 'Подразделение А']);
    $subdivisionB = Subdivision::query()->create(['name' => 'Подразделение Б']);

    $foremanA = createForemanForSubdivision($subdivisionA, 'В одном');
    $foremanOther = createForemanForSubdivision($subdivisionB, 'В другом');

    $application = Application::query()->create([
        'subdivision_id' => $subdivisionA->id,
        'user_id' => $foremanA->id,
        'responsible_user_id' => $foremanA->id,
        'desired_delivery_date' => now()->addDays(2),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'approved_by_user_id' => $foremanA->id,
    ]);

    $admin = createAdministrator();

    $this->actingAs($admin)
        ->post(route('users.reassign-applications.store', $foremanA), [
            'reassignments' => [(string) $application->id => (string) $foremanOther->id],
        ])
        ->assertRedirect(route('users.reassign-applications', $foremanA))
        ->assertSessionHasErrors();

    expect((int) $application->fresh()->user_id)->toBe((int) $foremanA->id);
});

function createAdministrator(): User
{
    return User::query()->create([
        'surname' => 'Админ',
        'name' => 'Системный',
        'patronymic' => 'Тест',
        'email' => 'admin-reassign-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ADMINISTRATOR_ROLE_ID,
    ]);
}

test('removing foreman from subdivision requires reassigning their applications in that subdivision', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $subdivisionA = Subdivision::query()->create(['name' => 'Снятие А']);
    $subdivisionB = Subdivision::query()->create(['name' => 'Снятие Б']);

    $foremanA = createForemanForSubdivision($subdivisionA, 'Снимаемый');
    $foremanA->assignedSubdivisions()->sync([$subdivisionA->id, $subdivisionB->id]);
    $foremanB = createForemanForSubdivision($subdivisionA, 'Принимающий');

    $application = Application::query()->create([
        'subdivision_id' => $subdivisionA->id,
        'user_id' => $foremanA->id,
        'responsible_user_id' => $foremanA->id,
        'desired_delivery_date' => now()->addDays(3),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'approved_by_user_id' => $foremanA->id,
    ]);

    $admin = createAdministrator();

    $this->actingAs($admin)
        ->put(route('foreman-subdivisions.update', $foremanA), [
            'subdivision_ids' => [(string) $subdivisionB->id],
        ])
        ->assertRedirect(route('foreman-subdivisions.edit', $foremanA))
        ->assertSessionHasErrors();

    expect($foremanA->assignedSubdivisions()->pluck('subdivisions.id')->map(fn ($id) => (int) $id)->all())
        ->toContain($subdivisionA->id);

    $this->actingAs($admin)
        ->put(route('foreman-subdivisions.update', $foremanA), [
            'subdivision_ids' => [(string) $subdivisionB->id],
            'reassignments' => [(string) $application->id => (string) $foremanB->id],
        ])
        ->assertRedirect(route('foreman-subdivisions.assignments'))
        ->assertSessionHas('status');

    $application->refresh();
    expect((int) $application->user_id)->toBe((int) $foremanB->id);
    expect((int) $application->responsible_user_id)->toBe((int) $foremanB->id);

    $assigned = $foremanA->assignedSubdivisions()->pluck('subdivisions.id')->map(fn ($id) => (int) $id)->all();
    expect($assigned)->toContain($subdivisionB->id);
    expect($assigned)->not->toContain($subdivisionA->id);
});

test('foreman subdivision update preview lists applications in removed subdivisions', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $subdivisionA = Subdivision::query()->create(['name' => 'Превью снятие']);
    $subdivisionB = Subdivision::query()->create(['name' => 'Превью остаётся']);

    $foremanA = createForemanForSubdivision($subdivisionA, 'Превью');
    $foremanA->assignedSubdivisions()->sync([$subdivisionA->id, $subdivisionB->id]);
    createForemanForSubdivision($subdivisionA, 'Замена');

    Application::query()->create([
        'subdivision_id' => $subdivisionA->id,
        'user_id' => $foremanA->id,
        'responsible_user_id' => $foremanA->id,
        'desired_delivery_date' => now()->addDays(2),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'approved_by_user_id' => $foremanA->id,
    ]);

    $admin = createAdministrator();

    $response = $this->actingAs($admin)->getJson(
        route('foreman-subdivisions.update.preview', $foremanA).'?'.http_build_query([
            'subdivision_ids' => [$subdivisionB->id],
        ])
    );

    $response->assertOk()
        ->assertJsonPath('requires_reassignment', true)
        ->assertJsonCount(1, 'applications');
});

function createForemanForSubdivision(Subdivision $subdivision, string $label): User
{
    $foreman = User::query()->create([
        'surname' => $label,
        'name' => 'Мастер',
        'patronymic' => 'Тест',
        'email' => 'foreman-'.$label.'-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => ForemanApplicationReassignment::FOREMAN_ROLE_ID,
    ]);
    $foreman->assignedSubdivisions()->sync([$subdivision->id]);

    return $foreman;
}
