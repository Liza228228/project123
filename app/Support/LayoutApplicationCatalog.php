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
    /** Категории встроенных макетов (сидеры, сценарии КП/акта). */
    public const CATEGORY_INSTALLATION_ACT = 'installation-act';

    public const CATEGORY_COMMERCIAL_PROPOSAL = 'commercial-proposal';

    /**
     * @return Collection<int, RequestLayout>
     */
    public static function layoutsForFillCatalog(): Collection
    {
        return RequestLayout::query()
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
