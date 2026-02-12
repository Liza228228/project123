<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->role !== User::ROLE_ADMINISTRATOR) {
            abort(403, 'Доступ разрешён только администраторам.');
        }

        return $next($request);
    }
}
