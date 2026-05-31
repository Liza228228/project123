<?php

// проверка доступа
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSiteForeman
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasRoleId(4)) {
            abort(403, 'Доступ разрешён только мастеру участка.');
        }

        return $next($request);
    }
}
