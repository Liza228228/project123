<?php

namespace App\Support;

use App\Models\DocumentHeaderLayout;
use App\Models\RequestLayout;
use Illuminate\Database\Eloquent\Builder;

/**
 * Исключение макетов «Коммерческое предложение» из каталогов отчётов и генератора.
 */
final class ReportLayoutCommercialProposal
{
    public const CATEGORY = 'commercial-proposal';

    public static function matchesTitle(string $title): bool
    {
        return mb_stripos($title, 'оммерческ') !== false;
    }

    public static function matchesSchema(?array $schema): bool
    {
        $schema = is_array($schema) ? $schema : [];

        return trim((string) ($schema['category'] ?? '')) === self::CATEGORY;
    }

    public static function isExcludedLayout(string $title, ?array $schema): bool
    {
        return self::matchesSchema($schema) || self::matchesTitle($title);
    }

    public static function isExcludedLayoutModel(RequestLayout $layout): bool
    {
        return self::isExcludedLayout(
            (string) $layout->title,
            is_array($layout->schema) ? $layout->schema : null
        );
    }

    /**
     * @param  Builder<RequestLayout>  $query
     * @return Builder<RequestLayout>
     */
    public static function scopeVisibleInReportCatalog(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q): void {
                $q->where('schema->category', '!=', self::CATEGORY)
                    ->orWhereNull('schema->category')
                    ->orWhere('schema->category', '');
            })
            ->where('title', 'not like', '%оммерческ%');
    }

    public static function purgeStoredLayouts(): void
    {
        RequestLayout::query()
            ->where(function (Builder $q): void {
                $q->where('schema->category', self::CATEGORY)
                    ->orWhere('title', 'like', '%оммерческ%');
            })
            ->each(fn (RequestLayout $layout) => $layout->delete());

        DocumentHeaderLayout::query()
            ->where('title', 'like', '%оммерческ%')
            ->each(fn (DocumentHeaderLayout $layout) => $layout->delete());
    }

    public static function abortIfExcluded(RequestLayout $layout): void
    {
        if (self::isExcludedLayoutModel($layout)) {
            abort(404);
        }
    }
}
