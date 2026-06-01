<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ApplicationController;
use App\Models\Application;
use App\Models\ApplicationItem;

$item = ApplicationItem::query()->find(345);
if ($item) {
    $item->update(['quantity' => 120]);
}

$application = Application::query()->with('items')->findOrFail(72);
$controller = app(ApplicationController::class);
$method = new ReflectionMethod($controller, 'rebalanceApprovedCatalogItemsForOrdering');
$method->setAccessible(true);
$method->invoke($controller, $application);

$application->refresh()->load('items');
foreach ($application->items as $row) {
    if ((int) ($row->equipment_id ?? 0) === 21 || $row->isCatalogOverflowPendingOrderLine()) {
        echo "Item #{$row->id} eq={$row->equipment_id} qty={$row->quantity} overflow=".($row->isCatalogOverflowPendingOrderLine() ? 'yes' : 'no')."\n";
    }
}
