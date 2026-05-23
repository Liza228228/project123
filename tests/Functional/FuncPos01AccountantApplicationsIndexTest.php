<?php

use App\Models\ApplicationArchive;
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('accountant applications index includes active archived and draft by default', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл бухгалтер');

    $accountant = User::query()->create([
        'surname' => 'Бухгалтер',
        'name' => 'Тест',
        'patronymic' => 'А',
        'email' => 'accountant-index-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ACCOUNTANT_ROLE_ID,
    ]);

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ]);

    $draft = Application::query()->first();
    expect($draft)->not->toBeNull();

    $active = Application::query()->create([
        'subdivision_id' => $ctx['subdivision']->id,
        'responsible_user_id' => $ctx['foreman']->id,
        'user_id' => $ctx['foreman']->id,
        'desired_delivery_date' => now()->addDays(5),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'approved_by_user_id' => $ctx['foreman']->id,
    ]);

    $archived = Application::query()->create([
        'subdivision_id' => $ctx['subdivision']->id,
        'responsible_user_id' => $ctx['foreman']->id,
        'user_id' => $ctx['foreman']->id,
        'desired_delivery_date' => now()->addDays(3),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_COMPLETED),
        'approved_by_user_id' => $ctx['foreman']->id,
    ]);
    ApplicationArchive::query()->create([
        'application_id' => $archived->id,
        'archived_at' => now(),
    ]);

    $response = $this->actingAs($accountant)->get(route('applications.index'));

    $response->assertOk()
        ->assertSee('№ '.$draft->id, false)
        ->assertSee('№ '.$active->id, false)
        ->assertSee('№ '.$archived->id, false);

    $this->actingAs($accountant)
        ->get(route('applications.show', $draft))
        ->assertOk();
});

test('accountant installation act browse has search and no layout fill button', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл акт');

    $accountant = User::query()->create([
        'surname' => 'Бухгалтер',
        'name' => 'Акт',
        'patronymic' => 'Т',
        'email' => 'accountant-act-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ACCOUNTANT_ROLE_ID,
    ]);

    Application::query()->create([
        'subdivision_id' => $ctx['subdivision']->id,
        'responsible_user_id' => $ctx['foreman']->id,
        'user_id' => $ctx['foreman']->id,
        'desired_delivery_date' => now()->addDays(5),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'approved_by_user_id' => $ctx['foreman']->id,
        'act_of_installation' => 'installation-acts/test.pdf',
    ]);

    $this->actingAs($accountant)
        ->get(route('applications.installation-act.browse'))
        ->assertOk()
        ->assertSee('browse-application-search', false)
        ->assertSee('Поиск по номеру, подразделению или дате', false)
        ->assertDontSee('Заполнить макет отчета', false);
});
