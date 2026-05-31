<?php

// вспомогательная логика
namespace App\Support;

use App\Models\RequestLayout;
use Illuminate\Database\Eloquent\Collection;
final class LayoutApplicationCatalog
{
    public const CATEGORY_INSTALLATION_ACT = 'installation-act';
    public static function layoutsForFillCatalog(): Collection
    {
        return ReportLayoutCommercialProposal::scopeVisibleInReportCatalog(
            RequestLayout::query()->orderBy('title')
        )->get(['id', 'title', 'schema']);
    }
}
