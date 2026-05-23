<?php

namespace App\Support;

use App\Models\RequestLayout;
use Database\Seeders\CommercialProposalRequestLayoutSeeder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Макеты, доступные в разделе «Отчеты по макетам» (заполнение и новый отчёт).
 */
final class LayoutApplicationCatalog
{
    /** @var list<string> */
    public const FILL_CATEGORIES = [
        'installation-act',
        'commercial-proposal',
    ];

    /**
     * @return Collection<int, RequestLayout>
     */
    public static function layoutsForFillCatalog(): Collection
    {
        return RequestLayout::query()
            ->whereIn('schema->category', self::FILL_CATEGORIES)
            ->orderBy('title')
            ->get(['id', 'title', 'schema']);
    }

    public static function commercialProposalLayout(): ?RequestLayout
    {
        return RequestLayout::query()
            ->where('title', CommercialProposalRequestLayoutSeeder::TITLE)
            ->first(['id', 'title', 'schema']);
    }
}
