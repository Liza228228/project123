<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\Equipment;

$eq = Equipment::query()->where('name', 'Регулятор давления')->first();
echo 'Equipment id: '.($eq?->id ?? 'none')."\n\n";

$items = ApplicationItem::query()
    ->withoutGlobalScopes()
    ->where(function ($q) use ($eq) {
        if ($eq) {
            $q->where('equipment_id', $eq->id);
        }
        $q->orWhere('base_name', 'like', '%Регулятор%');
    })
    ->where('quantity', '>=', 50)
    ->orderByDesc('application_id')
    ->limit(20)
    ->get(['id', 'application_id', 'equipment_id', 'base_name', 'quantity', 'is_checked', 'removed_at']);

foreach ($items as $i) {
    echo "app={$i->application_id} item={$i->id} eq={$i->equipment_id} base={$i->base_name} qty={$i->quantity} checked={$i->is_checked} removed=".($i->removed_at ?? 'null')."\n";
}
