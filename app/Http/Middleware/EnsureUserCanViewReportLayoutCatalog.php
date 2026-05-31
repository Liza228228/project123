<?php

// проверка доступа
namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureUserCanViewReportLayoutCatalog
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasAnyRoleId(User::REPORT_LAYOUT_CATALOG_VIEWER_ROLE_IDS)) {
            abort(403, 'Список макетов отчётов вам недоступен.');
        }

        return $next($request);
    }
}
