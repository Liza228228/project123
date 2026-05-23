<?php

namespace App\Http\Middleware;

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

        $allowed = $user->hasAnyRoleId([1, 6, 4, 2, 3, 7, \App\Models\User::ADMINISTRATOR_ROLE_ID]);

        if (! $allowed) {
            abort(403, 'Доступ к заявкам разрешён только директору, техническому директору, начальнику отдела снабжения, мастеру участка, начальнику котельной, бухгалтеру и администратору.');
        }

        return $next($request);
    }
}
