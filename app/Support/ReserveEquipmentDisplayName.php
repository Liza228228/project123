<?php

namespace App\Support;

use App\Models\ApplicationItem;
use App\Models\Equipment;
use App\Models\Scopes\ActiveApplicationItemScope;

class ReserveEquipmentDisplayName
{
    /** @var array<string, string> */
    private static array $bestNameCache = [];

    public static function isReserveEquipmentName(string $name): bool
    {
        return preg_match('/\[РЕЗЕРВ\s+заявка\s+\d+/u', $name) === 1;
    }

    public static function applicationIdFromReserveName(string $name): ?int
    {
        if (! preg_match('/\[РЕЗЕРВ\s+заявка\s+(\d+)/u', $name, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public static function shortBaseFromReserveName(string $name): string
    {
        $name = trim($name);
        if (str_contains($name, '+на согласовании')) {
            $name = trim((string) preg_replace('/\s*\(\+на согласовании.*$/u', '', $name));
        }

        return trim((string) preg_replace('/\s*\[РЕЗЕРВ\s+заявка\s+\d+.*$/u', '', $name));
    }

    public static function resolve(string $storedName, ?int $equipmentId = null): string
    {
        if (! self::isReserveEquipmentName($storedName)) {
            return $storedName;
        }

        $rebuilt = self::rebuildReserveEquipmentName($storedName, $equipmentId);

        return $rebuilt ?? $storedName;
    }

    public static function rebuildReserveEquipmentName(string $storedName, ?int $equipmentId = null): ?string
    {
        if (! self::isReserveEquipmentName($storedName)) {
            return null;
        }

        $applicationId = self::applicationIdFromReserveName($storedName);
        if ($applicationId === null) {
            return null;
        }

        $variant = 1;
        if (preg_match('/\[РЕЗЕРВ\s+заявка\s+\d+\]\s*\((\d+)\)/u', $storedName, $matches)) {
            $variant = max(1, (int) $matches[1]);
        }

        $short = self::sanitizedLabel(self::shortBaseFromReserveName($storedName));
        $human = self::sanitizedLabel(self::bestNameForApplication($applicationId, $short, $equipmentId));
        $isCorrupted = str_contains($storedName, '+на согласовании')
            || substr_count($storedName, '[РЕЗЕРВ') > 1;

        if ($human === '' || ($human === $short && ! $isCorrupted)) {
            return null;
        }

        if ($human === $short && $isCorrupted) {
            $human = $short;
        }

        return self::composeReservedName($human, $applicationId, $variant, $equipmentId);
    }

    public static function composeReservedName(
        string $humanBase,
        int $applicationId,
        int $variant = 1,
        ?int $excludeEquipmentId = null,
        ?int $itemIdForFallback = null
    ): string {
        $humanBase = self::sanitizedLabel($humanBase);
        $baseSuffix = ' [РЕЗЕРВ заявка '.$applicationId.']';

        for ($n = max(1, $variant); $n <= 50; $n++) {
            $suffix = $baseSuffix.($n === 1 ? '' : ' ('.$n.')');
            $maxBaseLength = 150 - mb_strlen($suffix);
            if ($maxBaseLength < 1) {
                $maxBaseLength = 1;
            }
            $candidate = mb_substr($humanBase, 0, $maxBaseLength).$suffix;
            $exists = Equipment::query()
                ->whereRaw('LOWER(name) = LOWER(?)', [$candidate])
                ->when($excludeEquipmentId !== null, fn ($q) => $q->where('id', '!=', $excludeEquipmentId))
                ->exists();
            if (! $exists) {
                return $candidate;
            }
        }

        $suffix = $baseSuffix.($itemIdForFallback !== null ? ' #'.$itemIdForFallback : ' #'.$applicationId);
        $maxBaseLength = 150 - mb_strlen($suffix);
        if ($maxBaseLength < 1) {
            $maxBaseLength = 1;
        }

        return mb_substr($humanBase, 0, $maxBaseLength).$suffix;
    }

    public static function bestNameForApplication(int $applicationId, string $prefix = '', ?int $equipmentId = null): string
    {
        $cacheKey = $applicationId.':'.mb_strtolower(trim($prefix)).':'.($equipmentId ?? 0);
        if (isset(self::$bestNameCache[$cacheKey])) {
            return self::$bestNameCache[$cacheKey];
        }

        $items = ApplicationItem::query()
            ->withoutGlobalScope(ActiveApplicationItemScope::class)
            ->where('application_id', $applicationId)
            ->with('manualDetail')
            ->get();

        $best = self::sanitizedLabel($prefix);
        foreach ($items as $item) {
            foreach (self::nameCandidatesFromItem($item) as $candidate) {
                if ($candidate === '') {
                    continue;
                }
                if ($prefix !== '' && ! self::namesMatchPrefix($candidate, $prefix)) {
                    continue;
                }
                if (mb_strlen($candidate) > mb_strlen($best)) {
                    $best = $candidate;
                }
            }
        }

        self::$bestNameCache[$cacheKey] = $best;

        return $best;
    }

    public static function repairEquipmentRecord(Equipment $equipment): bool
    {
        $rebuilt = self::rebuildReserveEquipmentName((string) $equipment->name, (int) $equipment->id);
        if ($rebuilt === null || $rebuilt === $equipment->name) {
            return false;
        }

        $equipment->update([
            'name' => mb_substr($rebuilt, 0, Equipment::NAME_MAX_LENGTH),
        ]);

        return true;
    }

    public static function repairApplication(int $applicationId): void
    {
        $equipmentIds = ApplicationItem::query()
            ->withoutGlobalScope(ActiveApplicationItemScope::class)
            ->where('application_id', $applicationId)
            ->whereNotNull('equipment_id')
            ->pluck('equipment_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->filter(fn (int $id): bool => $id > 0);

        foreach ($equipmentIds as $equipmentId) {
            $equipment = Equipment::query()->find($equipmentId);
            if ($equipment !== null && self::isReserveEquipmentName((string) $equipment->name)) {
                self::repairEquipmentRecord($equipment);
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function nameCandidatesFromItem(ApplicationItem $item): array
    {
        $candidates = [];

        $rawInput = self::sanitizedLabel(trim((string) ($item->raw_input ?? '')));
        if ($rawInput !== '') {
            $candidates[] = $rawInput;
        }

        $equipmentName = self::sanitizedLabel(trim((string) ($item->equipment_name ?? '')));
        if ($equipmentName !== '') {
            $candidates[] = $equipmentName;
        }

        $manualName = self::sanitizedLabel(trim((string) ($item->manualDetail?->equipment_name ?? '')));
        if ($manualName !== '') {
            $candidates[] = $manualName;
        }

        return $candidates;
    }

    private static function sanitizedLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        if (str_contains($label, '+на согласовании')) {
            $label = trim((string) preg_replace('/\s*\(\+на согласовании.*$/u', '', $label));
        }

        if (self::isReserveEquipmentName($label)) {
            $label = self::shortBaseFromReserveName($label);
        }

        return trim($label);
    }

    private static function namesMatchPrefix(string $candidate, string $prefix): bool
    {
        $candidateNorm = mb_strtolower(trim($candidate));
        $prefixNorm = mb_strtolower(trim($prefix));
        if ($prefixNorm === '') {
            return true;
        }

        return $candidateNorm === $prefixNorm
            || str_starts_with($candidateNorm, $prefixNorm)
            || str_starts_with($prefixNorm, $candidateNorm);
    }
}
