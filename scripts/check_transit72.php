<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ApplicationController;
use App\Models\Application;
use App\Support\ApplicationCatalogStockAvailability;

$application = Application::query()->with(['items.equipment', 'items.manualDetail'])->findOrFail(72);
$controller = app(ApplicationController::class);
$method = new ReflectionMethod($controller, 'rebalanceApprovedCatalogItemsForOrdering');
$method->setAccessible(true);
$method->invoke($controller, $application);
$application->refresh()->load(['items.equipment', 'items.manualDetail']);

$whMethod = new ReflectionMethod($controller, 'resolveMainWarehouseForAccounting');
$whMethod->setAccessible(true);
$mainWarehouse = $whMethod->invoke($controller);
$balanceMethod = new ReflectionMethod($controller, 'warehouseEquipmentBalance');
$balanceMethod->setAccessible(true);

foreach ($application->items as $item) {
    $item->setRelation('application', $application);
}

$physicalByItem = [];
foreach ($application->items as $item) {
    if (!$item->equipment_id) continue;
    $physicalByItem[$item->id] = (float) $balanceMethod->invoke($controller, (int)$item->equipment_id, (int)$mainWarehouse->id);
}

$item345 = $application->items->firstWhere('id', 345);
echo "Item 345 canMarkCatalog(100): ".($item345->canMarkCatalogDeliveryInTransit(100, $application->items) ? 'yes' : 'no')."\n";
echo "Item 345 shortage: ".$item345->catalogShortageQtyForMainWarehouseDelivery(100)."\n";
echo "Item 345 pendingOverflow: ".($item345->applicationHasPendingOverflowForCatalogItem($application->items) ? 'yes' : 'no')."\n";

$candidates = $application->items->filter(fn ($i) => $i->canMarkCatalogDeliveryInTransit((float)($physicalByItem[$i->id] ?? 0), $application->items));
echo "In transit candidates: ".$candidates->pluck('id')->implode(',')."\n";

$blocked = $application->items->filter(function ($i) use ($physicalByItem, $application) {
    if (!$i->canMarkDeliveryInTransit()) return false;
    return !$i->canMarkCatalogDeliveryInTransit((float)($physicalByItem[$i->id] ?? 0), $application->items);
});
echo "Blocked: ".$blocked->pluck('id')->implode(',')."\n";
