<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class ApplicationCommercialOfferDraft
{
    private const SESSION_KEY = 'application_commercial_offer_draft_path';

    private const SESSION_LINES_KEY = 'application_commercial_offer_draft_lines';

    private const APPLICATION_ID_KEY = 'application_commercial_offer_draft_application_id';

    /**
     * @param  list<array{equipment_name: string, quantity: int, quantity_unit: string, measurement_type: string}>  $lines
     */
    public static function store(string $pdfBinary, ?int $applicationId = null, array $lines = []): void
    {
        self::clear();
        $path = 'commercial-offer-drafts/'.uniqid('kp-', true).'.pdf';
        Storage::disk('local')->put($path, $pdfBinary);
        session([
            self::SESSION_KEY => $path,
            self::APPLICATION_ID_KEY => $applicationId,
            self::SESSION_LINES_KEY => $lines,
        ]);
    }

    public static function exists(): bool
    {
        return self::existsFor(null);
    }

    public static function existsFor(?int $applicationId): bool
    {
        $path = session(self::SESSION_KEY);
        if (! is_string($path) || $path === '' || ! Storage::disk('local')->exists($path)) {
            return false;
        }

        $draftApplicationId = session(self::APPLICATION_ID_KEY);

        if ($applicationId === null) {
            return $draftApplicationId === null;
        }

        return (int) $draftApplicationId === $applicationId;
    }

    public static function clear(): void
    {
        $path = session(self::SESSION_KEY);
        if (is_string($path) && $path !== '') {
            Storage::disk('local')->delete($path);
        }
        session()->forget([self::SESSION_KEY, self::APPLICATION_ID_KEY, self::SESSION_LINES_KEY]);
    }

    /**
     * @return list<array{equipment_name: string, quantity: int, quantity_unit: string, measurement_type: string}>
     */
    public static function pullLines(): array
    {
        $lines = session(self::SESSION_LINES_KEY);
        session()->forget(self::SESSION_LINES_KEY);

        if (! is_array($lines)) {
            return [];
        }

        $normalized = [];
        foreach ($lines as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['equipment_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $normalized[] = [
                'equipment_name' => $name,
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                'quantity_unit' => trim((string) ($row['quantity_unit'] ?? 'шт')) ?: 'шт',
                'measurement_type' => trim((string) ($row['measurement_type'] ?? 'piece')) ?: 'piece',
            ];
        }

        return $normalized;
    }

    public static function pullUploadedFile(?int $applicationId = null): ?UploadedFile
    {
        if (! self::existsFor($applicationId)) {
            return null;
        }

        $path = (string) session(self::SESSION_KEY);
        $absolute = Storage::disk('local')->path($path);
        if (! is_file($absolute)) {
            self::clear();

            return null;
        }

        $uploaded = new UploadedFile(
            $absolute,
            'kommercheskoe-predlozhenie.pdf',
            'application/pdf',
            null,
            true
        );
        self::clear();

        return $uploaded;
    }
}
