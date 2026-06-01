<?php

// функциональный тест
use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FunctionalScenarioFixture;

test('approved application without equipment positions cannot upload installation act', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл акт-пусто');

    $chief = User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Акт',
        'patronymic' => 'Пусто',
        'email' => 'chief-act-empty-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    $director = User::query()->create([
        'surname' => 'Директор',
        'name' => 'Акт',
        'patronymic' => 'Пусто',
        'email' => 'director-act-empty-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    Storage::fake('public');

    test()->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => '',
                'equipment_name' => '',
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
        'commercial_offer' => \Illuminate\Http\UploadedFile::fake()->create('kp.pdf', 100, 'application/pdf'),
    ]);

    $app = Application::query()->first();
    test()->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app));
    test()->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'commercial_offer_chief_is_checked' => '1',
    ]);
    test()->actingAs($director)->post(route('applications.approval', $app), [
        'commercial_offer_management_is_checked' => '1',
    ]);

    $app->refresh();
    expect($app->isStatusApproved())->toBeTrue();
    expect($app->items)->toHaveCount(0);
    expect($app->canUploadInstallationActAndPhotos())->toBeFalse();

    $uploadUrl = route('applications.installation-act.upload', ['application_id' => $app->id]);

    test()->actingAs($director)
        ->get(route('applications.show', $app))
        ->assertOk()
        ->assertDontSee($uploadUrl, false);
});

test('installation act upload is allowed only after catalog equipment is delivered to recipient warehouse', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Труба акт-доставка');

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(3)->toDateString(),
    ]);

    $item = ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 5,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_IN_TRANSIT_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    $application->load('items');
    expect($application->canUploadInstallationActAndPhotos())->toBeFalse();

    $item->update([
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
    ]);

    expect($application->fresh(['items'])->canUploadInstallationActAndPhotos())->toBeTrue();
});

test('installation act upload is allowed for partially approved application when agreed lines are delivered', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Труба акт-частично');

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_PARTIAL),
        'desired_delivery_date' => now()->addDays(3)->toDateString(),
    ]);

    ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 5,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);
    ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 2,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => false,
        'reason_not_selected' => 'Отклонено руководством',
    ]);

    $application->load('items');
    expect($application->isStatusPartial())->toBeTrue();
    expect($application->canUploadInstallationActAndPhotos())->toBeTrue();
});

test('commercial offer order button is hidden when catalog equipment is in transit', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Пила КП-путь');

    $director = User::query()->create([
        'surname' => 'Директор',
        'name' => 'КП',
        'patronymic' => 'Путь',
        'email' => 'director-kp-transit-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(3)->toDateString(),
        'commercial_offer' => 'commercial-offers/test-kp.pdf',
        'commercial_offer_chief_is_checked' => true,
        'commercial_offer_management_is_checked' => true,
        'approved_by_user_id' => $director->id,
        'management_supply_items_saved_at' => now(),
    ]);

    ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 2,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'reason_not_selected' => ApplicationItem::REASON_COMMERCIAL_OFFER_WAREHOUSE_RESERVE,
        'delivery_status_id' => ApplicationItem::DELIVERY_IN_TRANSIT_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    $application->load('items');
    expect($application->approvalLockedByShipmentProgress())->toBeTrue();
    expect($application->commercialOfferReadyForManualOrderLines())->toBeTrue();

    test()->actingAs($director)
        ->get(route('applications.show', $application))
        ->assertOk()
        ->assertDontSee('data-app-open-modal="commercial-offer-order-lines"', false)
        ->assertSee('В пути', false);
});
