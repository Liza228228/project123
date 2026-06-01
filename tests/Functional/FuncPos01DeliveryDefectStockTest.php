<?php

// функциональный тест
use App\Http\Controllers\ApplicationController;
use App\Models\Application;
use App\Models\ApplicationArchive;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\User;
use App\Support\WarehouseStockBucket;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('delivered stock can be marked defective and disposed on recipient warehouse', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Насос тестовый брак');
    MaterialStockMovement::query()->where('equipment_id', $ctx['equipment']->id)->delete();

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
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
    MaterialStockMovement::query()->create([
        'equipment_id' => $ctx['equipment']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'material_stock_movement_type_id' => $receiptTypeId,
        'quantity' => 5,
        'stock_bucket' => WarehouseStockBucket::GOOD,
        'comment' => 'Приход по доставке.',
    ]);

    WarehouseStockBucket::transferToDefective(
        (int) $ctx['equipment']->id,
        (int) $ctx['warehouse']->id,
        2.0,
        (int) $application->id,
        (int) $item->id,
        'Трещина корпуса',
        (int) $ctx['foreman']->id,
    );

    expect(WarehouseStockBucket::balance((int) $ctx['equipment']->id, (int) $ctx['warehouse']->id, WarehouseStockBucket::GOOD))->toBe(3.0);
    expect(WarehouseStockBucket::balance((int) $ctx['equipment']->id, (int) $ctx['warehouse']->id, WarehouseStockBucket::DEFECTIVE))->toBe(2.0);
    expect(WarehouseStockBucket::remainingDefectiveQuantityForApplicationItem((int) $application->id, (int) $item->id))->toBe(2.0);

    WarehouseStockBucket::disposeDefective(
        (int) $ctx['equipment']->id,
        (int) $ctx['warehouse']->id,
        1.0,
        (int) $application->id,
        (int) $item->id,
        'Утилизация на полигоне',
        (int) $ctx['foreman']->id,
    );

    expect(WarehouseStockBucket::balance((int) $ctx['equipment']->id, (int) $ctx['warehouse']->id, WarehouseStockBucket::DEFECTIVE))->toBe(1.0);
    expect(WarehouseStockBucket::remainingDefectiveQuantityForApplicationItem((int) $application->id, (int) $item->id))->toBe(1.0);
});

test('foreman can mark delivery defect via application route', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Клапан тестовый брак');
    MaterialStockMovement::query()->where('equipment_id', $ctx['equipment']->id)->delete();

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(2)->toDateString(),
    ]);

    $item = ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 3,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    MaterialStockMovement::query()->create([
        'equipment_id' => $ctx['equipment']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'material_stock_movement_type_id' => MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT),
        'quantity' => 3,
        'stock_bucket' => WarehouseStockBucket::GOOD,
        'comment' => 'Приход.',
    ]);

    $this->actingAs($ctx['foreman'])
        ->post(route('applications.delivery-defective', [$application, $item]), [
            'defect_quantity' => 1,
            'defect_reason' => 'Не работает после включения',
        ])
        ->assertRedirect(route('applications.show', $application));

    expect(WarehouseStockBucket::balance((int) $ctx['equipment']->id, (int) $ctx['warehouse']->id, WarehouseStockBucket::GOOD))->toBe(2.0);
    expect(WarehouseStockBucket::balance((int) $ctx['equipment']->id, (int) $ctx['warehouse']->id, WarehouseStockBucket::DEFECTIVE))->toBe(1.0);
});

test('defect quantity cannot be zero or exceed delivered amount', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Фильтр тестовый брак');
    MaterialStockMovement::query()->where('equipment_id', $ctx['equipment']->id)->delete();

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
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

    MaterialStockMovement::query()->create([
        'equipment_id' => $ctx['equipment']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'material_stock_movement_type_id' => MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT),
        'quantity' => 10,
        'stock_bucket' => WarehouseStockBucket::GOOD,
        'comment' => 'Приход.',
    ]);

    $this->actingAs($ctx['foreman'])
        ->from(route('applications.show', $application))
        ->post(route('applications.delivery-defective', [$application, $item]), [
            'defect_quantity' => 0,
            'defect_reason' => 'Не работает',
        ])
        ->assertRedirect(route('applications.show', $application))
        ->assertSessionHasErrors(['defect_quantity']);

    $this->actingAs($ctx['foreman'])
        ->from(route('applications.show', $application))
        ->post(route('applications.delivery-defective', [$application, $item]), [
            'defect_quantity' => 11,
            'defect_reason' => 'Не работает',
        ])
        ->assertRedirect(route('applications.show', $application))
        ->assertSessionHasErrors(['defect_quantity']);
});

test('dispose quantity cannot be zero or exceed remaining defective amount', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Задвижка тестовая утилизация');
    MaterialStockMovement::query()->where('equipment_id', $ctx['equipment']->id)->delete();

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(2)->toDateString(),
    ]);

    $item = ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 5,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    MaterialStockMovement::query()->create([
        'equipment_id' => $ctx['equipment']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'material_stock_movement_type_id' => MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT),
        'quantity' => 5,
        'stock_bucket' => WarehouseStockBucket::GOOD,
        'comment' => 'Приход.',
    ]);

    WarehouseStockBucket::transferToDefective(
        (int) $ctx['equipment']->id,
        (int) $ctx['warehouse']->id,
        2.0,
        (int) $application->id,
        (int) $item->id,
        'Повреждение',
        (int) $ctx['foreman']->id,
    );

    $this->actingAs($ctx['foreman'])
        ->from(route('applications.show', $application))
        ->post(route('applications.delivery-defective-dispose', [$application, $item]), [
            'dispose_quantity' => 0,
        ])
        ->assertRedirect(route('applications.show', $application))
        ->assertSessionHasErrors(['dispose_quantity']);

    $this->actingAs($ctx['foreman'])
        ->from(route('applications.show', $application))
        ->post(route('applications.delivery-defective-dispose', [$application, $item]), [
            'dispose_quantity' => 3,
        ])
        ->assertRedirect(route('applications.show', $application))
        ->assertSessionHasErrors(['dispose_quantity']);
});

test('disposed defective quantity reduces installation act write-off limit', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Компенсатор тестовый акт');
    MaterialStockMovement::query()->where('equipment_id', $ctx['equipment']->id)->delete();

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
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

    MaterialStockMovement::query()->create([
        'equipment_id' => $ctx['equipment']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'material_stock_movement_type_id' => MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT),
        'quantity' => 10,
        'stock_bucket' => WarehouseStockBucket::GOOD,
        'comment' => 'Приход.',
    ]);

    WarehouseStockBucket::transferToDefective(
        (int) $ctx['equipment']->id,
        (int) $ctx['warehouse']->id,
        3.0,
        (int) $application->id,
        (int) $item->id,
        'Повреждение',
        (int) $ctx['foreman']->id,
    );

    WarehouseStockBucket::disposeDefective(
        (int) $ctx['equipment']->id,
        (int) $ctx['warehouse']->id,
        3.0,
        (int) $application->id,
        (int) $item->id,
        'Утилизация',
        (int) $ctx['foreman']->id,
    );

    expect(WarehouseStockBucket::remainingInstallationIssueQuantity(
        10.0,
        (int) $application->id,
        (int) $item->id,
        (int) $ctx['equipment']->id,
        (int) $ctx['warehouse']->id,
    ))->toBe(7.0);

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
        [(int) $item->id => 8],
    ))->toThrow(Illuminate\Validation\ValidationException::class);

    $resolved = $resolveMethod->invoke(
        $controller,
        $application,
        $candidates,
        collect([(int) $item->id]),
        [(int) $item->id => 7],
    );

    expect($resolved[(int) $item->id])->toBe(7.0);
});

test('archived application cannot mark delivery defect on recipient warehouse', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Архив брак');
    MaterialStockMovement::query()->where('equipment_id', $ctx['equipment']->id)->delete();

    $application = Application::query()->create([
        'user_id' => $ctx['foreman']->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_COMPLETED),
        'desired_delivery_date' => now()->addDays(2)->toDateString(),
        'act_of_installation' => 'installation-acts/'.$ctx['subdivision']->id.'/archived-act.pdf',
    ]);

    ApplicationArchive::query()->create([
        'application_id' => $application->id,
        'archived_at' => now(),
    ]);

    $item = ApplicationItem::query()->create([
        'application_id' => $application->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 3,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
        'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
        'delivery_warehouse_id' => $ctx['warehouse']->id,
    ]);

    MaterialStockMovement::query()->create([
        'equipment_id' => $ctx['equipment']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'material_stock_movement_type_id' => MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT),
        'quantity' => 3,
        'stock_bucket' => WarehouseStockBucket::GOOD,
        'comment' => 'Приход.',
    ]);

    expect($application->fresh()->allowsRecipientWarehouseDefectManagement())->toBeFalse();

    $this->actingAs($ctx['foreman'])
        ->get(route('applications.show', $application))
        ->assertOk()
        ->assertDontSee('Брак на складе получателя', false);

    $this->actingAs($ctx['foreman'])
        ->post(route('applications.delivery-defective', [$application, $item]), [
            'defect_quantity' => 1,
            'defect_reason' => 'Повреждение после монтажа',
        ])
        ->assertRedirect(route('applications.show', $application))
        ->assertSessionHasErrors('defect');
});
