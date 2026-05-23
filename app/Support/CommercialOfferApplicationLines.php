<?php

namespace App\Support;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\RequestLayout;
use Illuminate\Support\Facades\Storage;

final class CommercialOfferApplicationLines
{
    private const STORAGE_DIR = 'commercial-offer-lines';

    /**
     * @return list<array{equipment_name: string, quantity: int, quantity_unit: string, measurement_type: string}>
     */
    public static function extractFromLayoutValues(RequestLayout $layout, array $values): array
    {
        $schema = is_array($layout->schema) ? $layout->schema : [];
        $tableKey = self::resolveCommercialTableFieldKey($schema);
        if ($tableKey === null) {
            return [];
        }

        $rows = $values[$tableKey] ?? null;
        if (! is_array($rows)) {
            return [];
        }

        $meta = ReportLayoutCommercialProposal::measurementMetaForUi();
        $lines = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row[0] ?? ''));
            if ($name === '') {
                continue;
            }
            $unit = trim((string) ($row[1] ?? ''));
            $qty = max(1, (int) round(self::parseNumber($row[2] ?? 1)));
            $measurementType = $meta['unitToType'][$unit] ?? $meta['defaultType'] ?? 'piece';
            if ($unit === '') {
                $unit = $meta['unitsByType'][$measurementType][0] ?? $meta['defaultUnit'] ?? 'шт';
            }

            $lines[] = [
                'equipment_name' => $name,
                'quantity' => $qty,
                'quantity_unit' => $unit,
                'measurement_type' => $measurementType,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{equipment_name: string, quantity: int, quantity_unit: string, measurement_type: string}>  $lines
     */
    public static function persistForApplication(int $applicationId, array $lines): void
    {
        if ($applicationId <= 0) {
            return;
        }

        $path = self::storagePath($applicationId);
        if ($lines === []) {
            Storage::disk('local')->delete($path);

            return;
        }

        Storage::disk('local')->put($path, json_encode($lines, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<array{equipment_name: string, quantity: int, quantity_unit: string, measurement_type: string}>
     */
    public static function loadForApplication(Application $application): array
    {
        $path = self::storagePath((int) $application->id);
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        $raw = json_decode((string) Storage::disk('local')->get($path), true);
        if (! is_array($raw)) {
            return [];
        }

        $lines = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['equipment_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $lines[] = [
                'equipment_name' => $name,
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                'quantity_unit' => trim((string) ($row['quantity_unit'] ?? 'шт')) ?: 'шт',
                'measurement_type' => trim((string) ($row['measurement_type'] ?? 'piece')) ?: 'piece',
            ];
        }

        return $lines;
    }

    public static function commitDraftToApplication(Application $application): void
    {
        $lines = ApplicationCommercialOfferDraft::pullLines();
        if ($lines === []) {
            return;
        }

        self::persistForApplication((int) $application->id, $lines);
    }

    /**
     * Строки таблицы КП для подстановки в форму «Как заказать» (без уже добавленных в заявку).
     *
     * @return list<array{equipment_name: string, quantity: int, quantity_unit: string, measurement_type: string}>
     */
    public static function linesForOrderFormPrefill(Application $application): array
    {
        $lines = self::loadForApplication($application);
        if ($lines === []) {
            return [];
        }

        $application->loadMissing('items');

        return array_values(array_filter(
            $lines,
            fn (array $line): bool => self::findMatchingItem($application, $line) === null
        ));
    }

    /**
     * Создаёт позиции заявки по таблице КП, если их ещё нет (для закупки и отображения).
     */
    public static function ensureItemsForProcurement(Application $application): void
    {
        $lines = self::loadForApplication($application);
        if ($lines === []) {
            return;
        }

        self::syncLinesToApplicationItems($application, $lines);

        if (! $application->isCommercialOfferReadyForProcurement()) {
            return;
        }

        $application->loadMissing('items');
        foreach ($application->items as $item) {
            if ($item->equipment_id !== null || $item->is_checked) {
                continue;
            }
            if (! self::itemMatchesStoredLines($item, $lines)) {
                continue;
            }
            $item->update([
                'is_checked' => true,
                'custom_equipment_supply_status_id' => ApplicationItem::CUSTOM_SUPPLY_ACCEPTED_ID,
            ]);
        }
    }

    /**
     * @param  list<array{equipment_name: string, quantity: int, quantity_unit: string, measurement_type: string}>  $lines
     */
    private static function itemMatchesStoredLines(ApplicationItem $item, array $lines): bool
    {
        $needle = self::lineFingerprintFromItem($item);
        foreach ($lines as $line) {
            if (self::lineFingerprint($line) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{equipment_name: string, quantity: int, quantity_unit: string, measurement_type: string}>  $lines
     */
    public static function syncLinesToApplicationItems(Application $application, array $lines): void
    {
        if ($lines === []) {
            return;
        }

        $application->loadMissing('items');
        $supplyReady = $application->isCommercialOfferReadyForProcurement();

        foreach ($lines as $line) {
            $existing = self::findMatchingItem($application, $line);
            if ($existing instanceof ApplicationItem) {
                continue;
            }

            $name = $line['equipment_name'];
            $application->items()->create([
                'equipment_id' => null,
                'equipment_name' => $name,
                'base_name' => $name,
                'size_value' => null,
                'quantity' => $line['quantity'],
                'measurement_type' => $line['measurement_type'],
                'quantity_unit' => $line['quantity_unit'],
                'raw_input' => null,
                'is_checked' => $supplyReady,
                'reason_not_selected' => null,
                'custom_equipment_supply_status_id' => $supplyReady
                    ? ApplicationItem::CUSTOM_SUPPLY_ACCEPTED_ID
                    : ApplicationItem::CUSTOM_SUPPLY_PENDING_APPROVAL_ID,
                'delivery_status_id' => null,
                'delivery_warehouse_id' => null,
            ]);
        }

        $application->unsetRelation('items');
    }

    /**
     * @param  array{equipment_name: string, quantity: int, quantity_unit: string, measurement_type: string}  $line
     */
    private static function findMatchingItem(Application $application, array $line): ?ApplicationItem
    {
        $needle = self::lineFingerprint($line);

        foreach ($application->items as $item) {
            if ($item->equipment_id !== null) {
                continue;
            }
            if (self::lineFingerprintFromItem($item) === $needle) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array{equipment_name: string, quantity: int, quantity_unit: string, measurement_type: string}  $line
     */
    private static function lineFingerprint(array $line): string
    {
        return mb_strtolower(trim($line['equipment_name']))
            .'|'.mb_strtolower(trim($line['quantity_unit']))
            .'|'.(int) $line['quantity'];
    }

    private static function lineFingerprintFromItem(ApplicationItem $item): string
    {
        $name = trim((string) ($item->equipment_name ?? $item->base_name ?? ''));

        return mb_strtolower($name)
            .'|'.mb_strtolower(trim((string) ($item->quantity_unit ?? 'шт')))
            .'|'.(int) $item->quantity;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private static function resolveCommercialTableFieldKey(array $schema): ?string
    {
        $fields = $schema['fields'] ?? [];
        if (! is_array($fields)) {
            return null;
        }

        $category = trim((string) ($schema['category'] ?? ''));

        foreach ($fields as $field) {
            if (! is_array($field) || ($field['type'] ?? '') !== 'table') {
                continue;
            }
            $mode = trim((string) ($field['table_mode'] ?? ''));
            if ($mode === ReportLayoutCommercialProposal::TABLE_MODE || $category === ReportLayoutCommercialProposal::CATEGORY) {
                $key = trim((string) ($field['key'] ?? ''));

                return $key !== '' ? $key : null;
            }
        }

        return null;
    }

    private static function parseNumber(mixed $raw): float
    {
        $s = str_replace(',', '.', (string) $raw);
        $s = preg_replace('/\s+/u', '', $s) ?? '';
        if ($s === '') {
            return 0.0;
        }
        $n = (float) $s;

        return is_finite($n) ? $n : 0.0;
    }

    private static function storagePath(int $applicationId): string
    {
        return self::STORAGE_DIR.'/'.$applicationId.'.json';
    }
}
