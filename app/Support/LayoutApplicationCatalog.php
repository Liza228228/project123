<?php

namespace App\Support;

use App\Models\RequestLayout;
use Illuminate\Database\Eloquent\Collection;

/**
 * Макеты, доступные в разделе «Отчеты по макетам» (заполнение и новый отчёт).
 */
final class LayoutApplicationCatalog
{
    public const CATEGORY_INSTALLATION_ACT = 'installation-act';

    /**
     * @return Collection<int, RequestLayout>
     */
    public static function layoutsForFillCatalog(): Collection
    {
        return ReportLayoutCommercialProposal::scopeVisibleInReportCatalog(
            RequestLayout::query()->orderBy('title')
        )->get(['id', 'title', 'schema']);
    }
}
