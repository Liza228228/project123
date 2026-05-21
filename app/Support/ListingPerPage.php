<?php

namespace App\Support;

use Illuminate\Http\Request;

final class ListingPerPage
{
    /**
     * @return array{perPage: int, allowedPerPage: list<int>, defaultPerPage: int}
     */
    public static function fromRequest(Request $request): array
    {
        $allowed = config('listing_pagination.allowed', [10, 15, 20, 30, 50]);
        if (! is_array($allowed) || $allowed === []) {
            $allowed = [10, 15, 20, 30, 50];
        }
        /** @var list<int> $allowed */
        $allowed = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $allowed)));
        sort($allowed);

        $default = (int) config('listing_pagination.default', 20);
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
