<?php

namespace App\Support;

use Illuminate\Http\Request;

final class RequestLayoutDocumentHeaderReturn
{
    public static function fromRequest(Request $request): ?string
    {
        return self::validate($request->query('return') ?? $request->input('return'));
    }

    public static function validate(mixed $return): ?string
    {
        $return = trim((string) $return);
        if ($return === '') {
            return null;
        }

        if (str_starts_with($return, '/')) {
            $return = url($return);
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if (! str_starts_with($return, $appUrl.'/')) {
            return null;
        }

        $path = (string) parse_url($return, PHP_URL_PATH);
        if (! preg_match('#^/boiler-chief/request-layouts/(create|\d+/edit)$#', $path)) {
            return null;
        }

        return $return;
    }

    public static function backLabel(?string $returnTo): string
    {
        if ($returnTo === null) {
            return 'К списку макетов шапок';
        }

        return str_contains($returnTo, '/edit') ? 'К редактированию макета' : 'К макету отчёта';
    }
}
