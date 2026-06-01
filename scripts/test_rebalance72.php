<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ApplicationController;
use App\Models\Application;
use App\Models\ApplicationItem;

$application = Application::query()->with('items')->findOrFail(72);
$controller = app(ApplicationController::class);
$method = new ReflectionMethod($controller, 'rebalanceApprovedCatalogItemsForOrdering');
$method->setAccessible(true);
$method->invoke($controller, $application);

$application->refresh();
$application->load('items');

foreach ($application->items as $item) {
    echo "Item #{$item->id} eq={$item->equipment_id} qty={$item->quantity} checked={$item->is_checked}\n";
    echo "  name: {$item->equipment_display_name}\n";
    echo "  custom_status: {$item->custom_equipment_supply_status_id}\n";
    echo "  canMarkCustomSupplyOrdered: ".($item->canMarkCustomSupplyOrdered() ? 'yes' : 'no')."\n";
}
