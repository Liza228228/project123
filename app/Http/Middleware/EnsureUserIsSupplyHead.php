<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSupplyHead
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyRoleId([1, 6, 2])) {
            abort(403, 'Раздел доступен только директору, техническому директору и начальнику отдела снабжения.');
        }

        return $next($request);
    }
}
