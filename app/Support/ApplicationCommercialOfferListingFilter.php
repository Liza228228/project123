<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class ApplicationCommercialOfferListingFilter
{
    public const KEY_ALL = 'all';

    public const KEY_WITH = 'with';

    public const KEY_WITHOUT = 'without';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::KEY_ALL => 'Все',
            self::KEY_WITH => 'С коммерческим предложением',
            self::KEY_WITHOUT => 'Без коммерческого предложения',
        ];
    }

    public static function normalize(mixed $value): string
    {
        $value = trim((string) $value);
        $allowed = array_keys(self::options());

        return in_array($value, $allowed, true) ? $value : self::KEY_ALL;
    }

    /**
     * @param  Builder<\App\Models\Application>  $query
     */
    public static function apply(Builder $query, string $filter): void
    {
        $filter = self::normalize($filter);

        match ($filter) {
            self::KEY_WITH => $query->whereNotNull('commercial_offer')
                ->whereRaw("TRIM(COALESCE(commercial_offer, '')) <> ''"),
            self::KEY_WITHOUT => $query->where(function (Builder $outer): void {
                $outer->whereNull('commercial_offer')
                    ->orWhereRaw("TRIM(COALESCE(commercial_offer, '')) = ''");
            }),
            default => null,
        };
    }
}
