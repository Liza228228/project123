<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Директор и технический директор — макеты шапок и конструктор макетов отчётов (PDF). */
class EnsureUserIsReportLayoutDesigner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasAnyRoleId(User::REPORT_LAYOUT_DESIGNER_ROLE_IDS)) {
            abort(403, 'Раздел доступен только директору и техническому директору.');
        }

        return $next($request);
    }
}
