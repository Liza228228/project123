<?php

// функциональный тест
use App\Http\Controllers\ApplicationController;
use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\User;
use App\Support\WarehouseStockBucket;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('installation act write-off uses partial quantity up to ordered amount', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Труба ПЭ 100');

    $director = User::query()->create([
        'surname' => 'Директор',
        'name' => 'Тест',
        'patronymic' => 'Кп',
        'email' => 'director-install-act-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(3)->toDateString(),
    ]);

    $item = ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 100,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
    MaterialStockMovement::query()->create([
        'equipment_id' => $ctx['equipment']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 100,
        'comment' => 'Тестовый приход для списания по акту.',
    ]);

    $application->load(['items.equipment.measurementUnit.unitType', 'items.manualDetail', 'subdivision']);

    $controller = app(ApplicationController::class);
    $method = new ReflectionMethod(ApplicationController::class, 'writeOffDeliveredItemsOnRecipientWarehouses');
    $method->setAccessible(true);
    $summary = $method->invoke(
        $controller,
        $application,
        $director,
        'Списание по акту установки (тест).',
        collect([$item->id]),
        [(int) $item->id => 95.0],
    );

    expect($summary['issued_lines'])->toBe(1);

    $issueTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
    $issuedQty = (float) MaterialStockMovement::query()
        ->where('equipment_id', $ctx['equipment']->id)
        ->where('warehouse_id', $ctx['warehouse']->id)
        ->where('material_stock_movement_type_id', $issueTypeId)
        ->sum('quantity');

    expect($issuedQty)->toBe(95.0);

    $remainingMethod = new ReflectionMethod(ApplicationController::class, 'remainingInstallationIssueQuantity');
    $remainingMethod->setAccessible(true);
    $application->refresh();
    $application->load('items');
    $remaining = $remainingMethod->invoke($controller, $application, $item->fresh());

    expect($remaining)->toBe(5.0);
});

test('completion archive recognizes installation act stock write-off correlation', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Труба архив акт');

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_PARTIAL),
        'desired_delivery_date' => now()->addDays(3)->toDateString(),
        'act_of_installation' => 'installation-acts/test-act.pdf',
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

    ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 1,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => false,
        'reason_not_selected' => 'Не требуется',
    ]);

    $application->installationActPhotos()->create([
        'path' => 'installation-act-photos/test.jpg',
    ]);

    $issueTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE);
    MaterialStockMovement::query()->create([
        'equipment_id' => $ctx['equipment']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'material_stock_movement_type_id' => $issueTypeId,
        'quantity' => 10,
        'stock_bucket' => WarehouseStockBucket::GOOD,
        'comment' => MaterialStockMovement::packCommentWithCorrelation(
            $application->installationStockIssueDocumentRefForItem((int) $item->id),
            'Списание по акту установки.',
        ),
    ]);

    $application->load(['items', 'installationActPhotos']);

    expect($application->catalogApprovedItemsFullyIssued())->toBeTrue();
    expect($application->qualifiesForCompletionArchive())->toBeTrue();
    expect($application->archiveIfEligible())->toBeTrue();
    expect($application->fresh()->isArchived())->toBeTrue();
});

test('installation act issue quantity validation rejects amount above ordered', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Труба ПЭ 200');

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(3)->toDateString(),
    ]);

    $item = ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 100,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    $application->load(['items.equipment.measurementUnit.unitType', 'items.manualDetail', 'items.deliveryWarehouse.subdivision']);

    $controller = app(ApplicationController::class);
    $candidatesMethod = new ReflectionMethod(ApplicationController::class, 'deliveredWarehouseIssueCandidates');
    $candidatesMethod->setAccessible(true);
    $candidates = $candidatesMethod->invoke($controller, $application);

    expect($candidates)->toHaveCount(1);

    $resolveMethod = new ReflectionMethod(ApplicationController::class, 'resolveInstallationActIssueQuantities');
    $resolveMethod->setAccessible(true);

    expect(fn () => $resolveMethod->invoke(
        $controller,
        $application,
        $candidates,
        collect([(int) $item->id]),
        [(int) $item->id => 101],
    ))->toThrow(Illuminate\Validation\ValidationException::class);
});
