<?php

// проверка доступа
namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureUserIsReportLayoutDesigner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasAnyRoleId(User::REPORT_LAYOUT_DESIGNER_ROLE_IDS)) {
            abort(403, 'Раздел доступен только директору, техническому директору и администратору.');
        }

        return $next($request);
    }
}
