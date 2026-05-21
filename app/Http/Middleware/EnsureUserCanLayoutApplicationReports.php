<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF «Заявки по макетам» и заполнение отчётов — все роли приложения.
 */
class EnsureUserCanLayoutApplicationReports
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->hasAnyRoleId(User::REPORT_LAYOUT_FILL_ROLE_IDS)) {
            abort(403, 'Заполнение отчётов недоступно для вашей роли.');
        }

        return $next($request);
    }
}
