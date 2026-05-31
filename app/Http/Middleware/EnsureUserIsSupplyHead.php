<?php

// проверка доступа
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSupplyHead
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyRoleId([1, 2, 3])) {
            abort(403, 'Раздел доступен только директору, начальнику отдела снабжения и бухгалтеру.');
        }

        return $next($request);
    }
}
