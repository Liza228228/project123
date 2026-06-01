<?php

// проверка доступа
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

        $allowed = $user->hasAnyRoleId(User::APPLICATION_LISTING_ROLE_IDS);

        if (! $allowed) {
            abort(403, 'Доступ к заявкам разрешён только директору, техническому директору, начальнику отдела снабжения, мастеру участка, начальнику котельной, бухгалтеру и администратору.');
        }

        return $next($request);
    }
}
