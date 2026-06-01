<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('application_items as ai')
    ->leftJoin('application_item_manual_details as md', 'md.application_item_id', '=', 'ai.id')
    ->whereNull('ai.removed_at')
    ->where(function ($q) {
        $q->where('ai.equipment_id', 21)
            ->orWhere('md.equipment_name', 'like', '%Регулятор давления%')
            ->orWhere('md.base_name', 'like', '%Регулятор%');
    })
    ->select('ai.id', 'ai.application_id', 'ai.equipment_id', 'ai.quantity', 'ai.is_checked', 'md.equipment_name', 'md.base_name')
    ->orderByDesc('ai.application_id')
    ->limit(30)
    ->get();

foreach ($rows as $r) {
    echo "app={$r->application_id} item={$r->id} eq={$r->equipment_id} qty={$r->quantity} checked={$r->is_checked} name={$r->equipment_name} base={$r->base_name}\n";
}
