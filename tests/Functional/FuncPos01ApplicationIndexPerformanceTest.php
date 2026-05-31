<?php

// функциональный тест
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

test('custom equipment order filter is visible only to supply management accountant and administrator', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл фильтр-своё');

    $supplyHead = User::query()->create([
        'surname' => 'Снаб',
        'name' => 'Фильтр',
        'patronymic' => '',
        'email' => 'supply-filter-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 2,
    ]);

    $accountant = User::query()->create([
        'surname' => 'Бух',
        'name' => 'Фильтр',
        'patronymic' => '',
        'email' => 'accountant-filter-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ACCOUNTANT_ROLE_ID,
    ]);

    $customOrderKey = \App\Support\ApplicationApprovalListingFilter::KEY_NEEDS_CUSTOM_ORDER;

    expect(\App\Support\ApplicationApprovalListingFilter::optionGroupsForUser($ctx['foreman'])['Исполнение'] ?? [])
        ->not->toHaveKey($customOrderKey);
    expect(\App\Support\ApplicationApprovalListingFilter::optionGroupsForUser($supplyHead)['Исполнение'])
        ->toHaveKey($customOrderKey);
    expect(\App\Support\ApplicationApprovalListingFilter::optionGroupsForUser($accountant)['Исполнение'])
        ->toHaveKey($customOrderKey);

    expect(\App\Support\ApplicationApprovalListingFilter::normalizeForUser($customOrderKey, $ctx['foreman']))
        ->toBe(\App\Support\ApplicationApprovalListingFilter::KEY_ALL);
    expect(\App\Support\ApplicationApprovalListingFilter::normalizeForUser($customOrderKey, $supplyHead))
        ->toBe($customOrderKey);
});
