<?php

// сервис
namespace App\Services;

use App\Models\Application;
use App\Models\Subdivision;
use App\Models\TransportOption;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class ApplicationChangeRecorder
{
    public static function snapshot(Application $application): array
    {
        $application->loadMissing([
            'subdivision',
            'responsibleUser',
            'transportOption',
            'items.equipment.measurementUnit.unitType',
            'items.manualDetail',
        ]);

        return [
            'subdivision_id' => $application->subdivision_id,
            'responsible_user_id' => $application->responsible_user_id,
            'transport_option_id' => $application->transport_option_id,
            'desired_delivery_date' => $application->desired_delivery_date->format('Y-m-d'),
            'approved_by_user_id' => $application->approved_by_user_id,
            'application_status_id' => $application->application_status_id,
            'items' => $application->items->mapWithKeys(fn ($i) => [
                $i->id => [
                    'label' => $i->equipment_display_name,
                    'quantity' => (int) $i->quantity,
                    'is_checked' => (bool) $i->is_checked,
                ],
            ])->all(),
        ];
    }
    public static function diff(array $before, Application $after): array
    {
        $after->loadMissing([
            'subdivision',
            'responsibleUser',
            'transportOption',
            'items.equipment.measurementUnit.unitType',
            'items.manualDetail',
        ]);

        $lines = [];

        if ((int) $before['subdivision_id'] !== (int) $after->subdivision_id) {
            $oldName = Subdivision::find($before['subdivision_id'])?->name ?? '—';
            $lines[] = 'Подразделение: «'.$oldName.'» → «'.$after->subdivision->name.'»';
        }

        $oldRespId = $before['responsible_user_id'] !== null ? (int) $before['responsible_user_id'] : null;
        $newRespId = $after->responsible_user_id !== null ? (int) $after->responsible_user_id : null;
        if ($oldRespId !== $newRespId) {
            $oldR = $oldRespId ? User::find($oldRespId) : null;
            $newR = $after->responsibleUser;
            $oldLabel = $oldR ? trim($oldR->surname.' '.$oldR->name) : '—';
            $newLabel = $newR ? trim($newR->surname.' '.$newR->name) : '—';
            $lines[] = 'Ответственный: '.$oldLabel.' → '.$newLabel;
        }

        $oldTid = $before['transport_option_id'] !== null ? (int) $before['transport_option_id'] : null;
        $newTid = $after->transport_option_id !== null ? (int) $after->transport_option_id : null;
        if ($oldTid !== $newTid) {
            $oldT = self::transportOptionSummaryLabel($oldTid);
            $newT = self::transportOptionSummaryLabel($newTid);
            $lines[] = 'Транспорт / доставка: «'.$oldT.'» → «'.$newT.'»';
        }

        if ($before['desired_delivery_date'] !== $after->desired_delivery_date->format('Y-m-d')) {
            $oldD = Carbon::parse($before['desired_delivery_date'])->format('d.m.Y');
            $newD = $after->desired_delivery_date->format('d.m.Y');
            $lines[] = 'Желаемая дата поставки: '.$oldD.' → '.$newD;
        }

        if (! empty($before['approved_by_user_id']) && $after->approved_by_user_id === null) {
            $lines[] = 'Согласование сброшено; заявка снова на рассмотрении.';
        }
        $beforeItems = $before['items'];
        $afterItemsKeyed = $after->items->mapWithKeys(fn ($i) => [
            $i->id => [
                'label' => $i->equipment_display_name,
                'quantity' => (int) $i->quantity,
                'is_checked' => (bool) $i->is_checked,
            ],
        ])->all();

        foreach ($beforeItems as $id => $row) {
            if ($row['is_checked']) {
                continue;
            }
            if (! isset($afterItemsKeyed[$id])) {
                $lines[] = 'Удалена позиция (не была согласована): «'.$row['label'].'» × '.$row['quantity'];
            }
        }

        foreach ($afterItemsKeyed as $id => $row) {
            if (! isset($beforeItems[$id])) {
                $lines[] = 'Добавлена позиция: «'.$row['label'].'» × '.$row['quantity'];
            }
        }

        foreach ($afterItemsKeyed as $id => $rowA) {
            if (! isset($beforeItems[$id])) {
                continue;
            }
            $rowB = $beforeItems[$id];
            if ($rowB['is_checked']) {
                continue;
            }
            if ($rowA['label'] !== $rowB['label'] || $rowA['quantity'] !== $rowB['quantity']) {
                $lines[] = 'Изменена позиция: «'.$rowB['label'].'» × '.$rowB['quantity'].' → «'.$rowA['label'].'» × '.$rowA['quantity'];
            }
        }

        return $lines;
    }

    private static function transportOptionSummaryLabel(?int $id): string
    {
        if ($id === null) {
            return '—';
        }

        $t = TransportOption::query()->find($id);
        if (! $t) {
            return '—';
        }

        $name = trim((string) ($t->name ?? ''));
        if (Schema::hasColumn('transport_options', 'plate')) {
            $plate = trim((string) ($t->plate ?? ''));
            if ($name !== '' && $plate !== '') {
                return $name.' — '.$plate;
            }
            if ($plate !== '') {
                return $plate;
            }
        }

        return $name !== '' ? $name : '—';
    }
    public static function equipmentDiff(array $before, Application $after): array
    {
        $after->loadMissing(['items.equipment.measurementUnit.unitType', 'items.manualDetail']);

        $lines = [];
        $beforeItems = $before['items'];
        $afterItemsKeyed = $after->items->mapWithKeys(fn ($i) => [
            $i->id => [
                'label' => $i->equipment_display_name,
                'quantity' => (int) $i->quantity,
                'is_checked' => (bool) $i->is_checked,
            ],
        ])->all();

        foreach ($beforeItems as $id => $row) {
            if ($row['is_checked']) {
                continue;
            }
            if (! isset($afterItemsKeyed[$id])) {
                $lines[] = 'Удалена позиция (не была согласована): «'.$row['label'].'» × '.$row['quantity'];
            }
        }

        foreach ($afterItemsKeyed as $id => $row) {
            if (! isset($beforeItems[$id])) {
                $lines[] = 'Добавлена позиция: «'.$row['label'].'» × '.$row['quantity'];
            }
        }

        foreach ($afterItemsKeyed as $id => $rowA) {
            if (! isset($beforeItems[$id])) {
                continue;
            }
            $rowB = $beforeItems[$id];
            if ($rowB['is_checked']) {
                continue;
            }
            if ($rowA['label'] !== $rowB['label'] || $rowA['quantity'] !== $rowB['quantity']) {
                $lines[] = 'Изменена позиция: «'.$rowB['label'].'» × '.$rowB['quantity'].' → «'.$rowA['label'].'» × '.$rowA['quantity'];
            }
        }

        return $lines;
    }
}
