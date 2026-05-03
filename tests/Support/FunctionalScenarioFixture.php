<?php

namespace Tests\Support;

use App\Models\ApplicationStatus;
use App\Models\Equipment;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\MeasurementUnit;
use App\Models\Subdivision;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\MeasurementUnitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

final class FunctionalScenarioFixture
{
    public static function seedRolesAndUnits(): void
    {
        ApplicationStatus::forgetIdCache();
        MaterialStockMovementType::forgetIdCache();

        (new RoleSeeder)->run();
        (new MeasurementUnitSeeder)->run();
    }

    /**
     * Основной склад «Администрация» и остаток каталожного оборудования для мастера участка.
     *
     * @return array{foreman: User, subdivision: Subdivision, equipment: Equipment, warehouse: Warehouse}
     */
    public static function foremanCatalogStockContext(string $equipmentName = 'Котёл КВ-100'): array
    {
        $subdivision = Subdivision::query()->create(['name' => 'Киренск левый берег']);

        $foreman = User::query()->create([
            'surname' => 'Тестов',
            'name' => 'Мастер',
            'patronymic' => 'Участкович',
            'email' => 'foreman-func-'.uniqid('', true).'@test.local',
            'password' => Hash::make('password'),
            'role_id' => 4,
        ]);
        $foreman->assignedSubdivisions()->sync([$subdivision->id]);

        $pieceUnitId = (int) MeasurementUnit::query()
            ->where('code', 'шт')
            ->whereHas('unitType', fn ($q) => $q->where('code', 'piece'))
            ->value('id');
        if ($pieceUnitId <= 0) {
            throw new \RuntimeException('Не найдена единица «шт» для типа piece.');
        }

        $equipment = Equipment::query()->create([
            'name' => $equipmentName,
            'value' => null,
            'measurement_unit_id' => $pieceUnitId,
            'is_catalog' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'name' => 'Склад Администрация (тест)',
            'code' => 'FN'.substr(preg_replace('/\D/', '', uniqid()), -6),
            'subdivision_id' => $subdivision->id,
            'is_primary' => true,
        ]);

        Warehouse::query()->where('id', '!=', $warehouse->id)->update(['is_primary' => false]);

        $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
        MaterialStockMovement::query()->create([
            'equipment_id' => $equipment->id,
            'warehouse_id' => $warehouse->id,
            'material_stock_movement_type_id' => $receiptTypeId,
            'quantity' => 100,
        ]);

        return [
            'foreman' => $foreman,
            'subdivision' => $subdivision,
            'equipment' => $equipment,
            'warehouse' => $warehouse,
        ];
    }

    public static function primaryAdministrationWarehouse(): Warehouse
    {
        $subdivision = Subdivision::query()->firstOrCreate(
            ['name' => 'Служебное подразделение (тест материалов)'],
        );

        Warehouse::query()->update(['is_primary' => false]);

        return Warehouse::query()->create([
            'name' => 'Администрация (основной склад тест)',
            'code' => 'AD'.substr(preg_replace('/\D/', '', uniqid()), -6),
            'subdivision_id' => $subdivision->id,
            'is_primary' => true,
        ]);
    }
}
