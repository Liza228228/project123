<?php

// функциональный тест
use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\User;
use App\Support\ReportLayoutEquipmentApplications;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('report layout equipment list for foreman includes only own delivered applications', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Оборудование отчёт');

    $otherForeman = User::query()->create([
        'surname' => 'Другой',
        'name' => 'Мастер',
        'patronymic' => 'Тест',
        'email' => 'other-foreman-report-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 4,
    ]);
    $otherForeman->assignedSubdivisions()->sync([$ctx['subdivision']->id]);

    $ownApplication = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(2)->toDateString(),
    ]);
    ApplicationItem::query()->create([
        'application_id' => $ownApplication->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 2,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    $otherApplication = Application::query()->create([
        'user_id' => $otherForeman->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(2)->toDateString(),
    ]);
    ApplicationItem::query()->create([
        'application_id' => $otherApplication->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 1,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    $pendingApplication = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(2)->toDateString(),
    ]);
    ApplicationItem::query()->create([
        'application_id' => $pendingApplication->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 5,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
    ]);

    $options = ReportLayoutEquipmentApplications::clientOptionsForUser($ctx['foreman']);
    $ids = array_column($options, 'id');

    expect($ids)->toContain((int) $ownApplication->id);
    expect($ids)->not->toContain((int) $otherApplication->id);
    expect($ids)->not->toContain((int) $pendingApplication->id);
});

test('report layout equipment list for director includes all delivered applications', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Оборудование директор');

    $director = User::query()->create([
        'surname' => 'Директор',
        'name' => 'Отчёт',
        'patronymic' => 'Тест',
        'email' => 'director-report-layout-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(2)->toDateString(),
    ]);
    ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 3,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    $options = ReportLayoutEquipmentApplications::clientOptionsForUser($director);
    $ids = array_column($options, 'id');

    expect($ids)->toContain((int) $application->id);
});

test('report layout equipment list for administrator includes all delivered applications', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Оборудование админ');

    $admin = User::query()->create([
        'surname' => 'Админ',
        'name' => 'Отчёт',
        'patronymic' => 'Тест',
        'email' => 'admin-report-layout-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => User::ADMINISTRATOR_ROLE_ID,
    ]);

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(2)->toDateString(),
    ]);
    ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 1,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    $options = ReportLayoutEquipmentApplications::clientOptionsForUser($admin);
    $ids = array_column($options, 'id');

    expect($ids)->toContain((int) $application->id);
});

test('installation act report layout list excludes archive act and partial delivery', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Акт отчёт фильтр');

    $eligible = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'responsible_user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(2)->toDateString(),
    ]);
    ApplicationItem::query()->create([
        'application_id' => $eligible->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 2,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    $withAct = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'responsible_user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(2)->toDateString(),
        'act_of_installation' => 'installation-acts/test.pdf',
    ]);
    ApplicationItem::query()->create([
        'application_id' => $withAct->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 1,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    $partialDelivery = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'responsible_user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(2)->toDateString(),
    ]);
    ApplicationItem::query()->create([
        'application_id' => $partialDelivery->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 5,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);
    ApplicationItem::query()->create([
        'application_id' => $partialDelivery->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 3,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
    ]);

    $options = ReportLayoutEquipmentApplications::clientOptionsForInstallationActUser($ctx['foreman']);
    $ids = array_column($options, 'id');

    expect($ids)->toContain((int) $eligible->id);
    expect($ids)->not->toContain((int) $withAct->id);
    expect($ids)->not->toContain((int) $partialDelivery->id);
});

test('installation act equipment quantity excludes marked defective stock', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Компенсатор акт PDF');

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'responsible_user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(2)->toDateString(),
    ]);

    $item = ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 10,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    \App\Models\MaterialStockMovement::query()->create([
        'equipment_id' => $ctx['equipment']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'material_stock_movement_type_id' => \App\Models\MaterialStockMovementType::idFor(\App\Models\MaterialStockMovementType::NAME_RECEIPT),
        'quantity' => 10,
        'stock_bucket' => \App\Support\WarehouseStockBucket::GOOD,
        'comment' => 'Приход.',
    ]);

    \App\Support\WarehouseStockBucket::transferToDefective(
        (int) $ctx['equipment']->id,
        (int) $ctx['warehouse']->id,
        3.0,
        (int) $application->id,
        (int) $item->id,
        'Повреждение',
        (int) $ctx['foreman']->id,
    );

    $options = ReportLayoutEquipmentApplications::clientOptionsForInstallationActUser($ctx['foreman']);
    $row = collect($options)->firstWhere('id', (int) $application->id);

    expect($row)->not->toBeNull();
    expect($row['equipment'][0]['quantity'] ?? '')->toBe('7 шт');
});
