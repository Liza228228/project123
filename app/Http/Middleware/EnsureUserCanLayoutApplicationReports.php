<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF «Заявки по макетам»: начальник котельной и бухгалтер.
 */
class EnsureUserCanLayoutApplicationReports
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->hasAnyRoleId(User::LAYOUT_APPLICATION_REPORT_ROLE_IDS)) {
            abort(403, 'Раздел доступен только начальнику котельной и бухгалтеру.');
        }

        return $next($request);
    }
}
