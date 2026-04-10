<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->isAdmin() && ! $user->isTeacher()) {
            return $next($request);
        }

        if ($user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        if ($request->routeIs('settings.*', 'logout', 'two-factor.*', 'user-password.*', 'password.*')) {
            return $next($request);
        }

        return redirect()
            ->route('settings.edit')
            ->with('error', 'Para continuar, ative a autenticação em dois fatores nas configurações da conta.');
    }
}
