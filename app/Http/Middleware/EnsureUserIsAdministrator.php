<?php

// проверка доступа
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasRoleId(\App\Models\User::ADMINISTRATOR_ROLE_ID)) {
            abort(403, 'Доступ разрешён только администраторам.');
        }

        return $next($request);
    }
}
