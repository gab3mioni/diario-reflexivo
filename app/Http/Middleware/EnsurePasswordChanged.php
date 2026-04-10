<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            $allowed = [
                'password.force.show',
                'password.force.update',
                'logout',
            ];

            if (! in_array($request->route()?->getName(), $allowed, true)) {
                return redirect()->route('password.force.show');
            }
        }

        return $next($request);
    }
}
