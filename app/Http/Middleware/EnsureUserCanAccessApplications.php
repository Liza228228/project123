<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessApplications
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Необходима авторизация.');
        }

        $allowed = in_array($user->role, [
            User::ROLE_DIRECTOR,
            User::ROLE_SITE_FOREMAN,
            User::ROLE_SUPPLY_DEPARTMENT_HEAD,
        ], true);

        if (! $allowed) {
            abort(403, 'Доступ к заявкам разрешён только директору, начальнику отдела снабжения и мастеру участка.');
        }

        return $next($request);
    }
}
