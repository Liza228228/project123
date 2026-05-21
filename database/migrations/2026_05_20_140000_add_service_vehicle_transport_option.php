<?php

use App\Models\TransportOption;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transport_options')) {
            return;
        }

        $hasPlate = Schema::hasColumn('transport_options', 'plate');
        $methodAttrs = ['name' => TransportOption::NAME_SERVICE_VEHICLE];
        if ($hasPlate) {
            $methodAttrs['plate'] = null;
            $methodAttrs['label'] = null;
        }

        TransportOption::query()->firstOrCreate(
            array_merge(['name' => TransportOption::NAME_SERVICE_VEHICLE], $hasPlate ? ['plate' => null] : []),
            $hasPlate ? ['label' => null] : []
        );

        if (! $hasPlate) {
            return;
        }

        foreach (['777', '888'] as $plate) {
            TransportOption::query()->updateOrCreate(
                ['plate' => $plate],
                [
                    'name' => TransportOption::NAME_SERVICE_VEHICLE,
                    'label' => null,
                ]
            );
        }
    }

    public function down(): void
    {
        // Справочник транспорта не откатываем — записи могут использоваться в заявках.
    }
};
