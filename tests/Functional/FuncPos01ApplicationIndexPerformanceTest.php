<?php

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\Subdivision;
use App\Models\User;
use App\Support\ApplicationIndexPresenter;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('application index presenter precomputes list status for applications', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл презентер');

    $application = Application::query()->create([
        'subdivision_id' => $ctx['subdivision']->id,
        'user_id' => $ctx['foreman']->id,
        'responsible_user_id' => $ctx['foreman']->id,
        'desired_delivery_date' => now()->addDays(2),
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'approved_by_user_id' => $ctx['foreman']->id,
    ]);

    ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 1,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
    ]);

    $application->load([
        'items.equipment:id,name',
        'user:id,surname,name,patronymic,role_id',
        'applicationStatus:id,name',
        'archive',
    ]);

    $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
        collect([$application]),
        1,
        15,
        1
    );

    ApplicationIndexPresenter::prepare($paginator, $ctx['foreman']);

    expect($application->index_list_status)->toBe('approved');
    expect($application->index_needs_custom_order)->toBeFalse();
});

test('subdivision boiler chief lookup is cached per request', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $subdivision = Subdivision::query()->create(['name' => 'Кэш котельной']);

    Subdivision::resetBoilerChiefCache();

    expect(Subdivision::hasBoilerChiefAssigned((int) $subdivision->id))->toBeFalse();

    $chief = User::query()->create([
        'surname' => 'Нач',
        'name' => 'Кэш',
        'patronymic' => '',
        'email' => 'chief-cache-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$subdivision->id]);

    expect(Subdivision::hasBoilerChiefAssigned((int) $subdivision->id))->toBeFalse();

    Subdivision::resetBoilerChiefCache();

    expect(Subdivision::hasBoilerChiefAssigned((int) $subdivision->id))->toBeTrue();
});
