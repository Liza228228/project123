<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ApplicationController;
use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\Equipment;
use App\Support\ApplicationCatalogStockAvailability;
use App\Support\WarehouseStockBucket;
use Illuminate\Support\Facades\DB;

$applicationId = (int) ($argv[1] ?? 71);
$application = Application::query()->with(['items.manualDetail', 'items.equipment'])->find($applicationId);
if (! $application) {
    echo "Application {$applicationId} not found\n";
    exit(1);
}

echo "Application {$applicationId}\n";
echo 'approved_by: '.($application->approved_by_user_id ?? 'null')."\n";
echo 'mgmt_supply_saved: '.($application->management_supply_items_saved_at ?? 'null')."\n";
echo 'supply_workflow: '.($application->isSupplyApprovedForCustomEquipmentWorkflow() ? 'yes' : 'no')."\n\n";

$controller = app(ApplicationController::class);
$ref = new ReflectionClass($controller);
$whMethod = $ref->getMethod('resolveMainWarehouseForAccounting');
$whMethod->setAccessible(true);
$mainWarehouse = $whMethod->invoke($controller);
echo 'Main warehouse: '.($mainWarehouse?->name ?? 'NULL').' id='.($mainWarehouse?->id ?? '')."\n\n";

foreach ($application->items as $item) {
    echo "Item #{$item->id}\n";
    echo "  equipment_id: ".($item->equipment_id ?? 'null')."\n";
    echo "  base_name: ".($item->base_name ?? '')."\n";
    echo "  equipment_name (accessor): ".($item->equipment_name ?? '')."\n";
    echo "  qty: {$item->quantity} checked: ".(int) $item->is_checked."\n";
    echo "  custom_status: ".($item->custom_equipment_supply_status_id ?? 'null')."\n";
    echo "  delivery_status: ".($item->delivery_status_id ?? 'null')."\n";
    echo "  raw_input: ".($item->raw_input ?? '')."\n";
    echo "  canMarkCustomSupplyOrdered: ".($item->canMarkCustomSupplyOrdered() ? 'yes' : 'no')."\n";
    echo "  canMarkDeliveryInTransit: ".($item->canMarkDeliveryInTransit() ? 'yes' : 'no')."\n";

    if ($item->equipment_id) {
        $eid = (int) $item->equipment_id;
        $physical = WarehouseStockBucket::balance($eid, (int) $mainWarehouse->id, WarehouseStockBucket::GOOD);
        $available = ApplicationCatalogStockAvailability::availableOnMainWarehouse(
            $eid,
            $physical,
            $applicationId,
            null
        );
        $eq = Equipment::find($eid);
        echo "  equipment catalog name: ".($eq?->name ?? '')."\n";
        echo "  physical balance: {$physical}\n";
        echo "  available (excl this app): {$available}\n";
        $req = (int) $item->quantity;
        echo "  overflow would be: ".max(0, $req - (int) floor($available))."\n";
    }
    echo "\n";
}

$regulator = Equipment::query()->where('name', 'like', '%Регулятор%')->orWhere('name', 'like', '%регулятор%')->get(['id', 'name', 'is_catalog']);
echo "Regulator equipment in catalog:\n";
foreach ($regulator as $eq) {
    $bal = $mainWarehouse ? WarehouseStockBucket::balance((int) $eq->id, (int) $mainWarehouse->id, WarehouseStockBucket::GOOD) : 0;
    echo "  id={$eq->id} name={$eq->name} balance={$bal}\n";
}
