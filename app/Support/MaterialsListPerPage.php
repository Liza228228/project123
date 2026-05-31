<?php

// вспомогательная логика
namespace App\Support;

use Illuminate\Http\Request;

final class MaterialsListPerPage
{
    public static function fromRequest(Request $request, string $section): array
    {
        $allowed = config("materials_lists.{$section}.allowed", [10, 15, 20, 30, 50]);
        if (! is_array($allowed) || $allowed === []) {
            $allowed = [10, 15, 20, 30, 50];
        }
        $allowed = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $allowed)));
        sort($allowed);

        $default = (int) config("materials_lists.{$section}.default", $allowed[0]);
        if (! in_array($default, $allowed, true)) {
            $default = $allowed[0];
        }

        $perPage = (int) $request->integer('per_page', $default);
        if (! in_array($perPage, $allowed, true)) {
            $perPage = $default;
        }

        return [
            'perPage' => $perPage,
            'allowedPerPage' => $allowed,
            'defaultPerPage' => $default,
        ];
    }
}
