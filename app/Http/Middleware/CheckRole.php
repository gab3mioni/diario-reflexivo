<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que verifica se o utilizador possui uma role específica.
 */
class CheckRole
{
    /**
     * Verifica se o utilizador autenticado possui a role exigida pela rota.
     *
     * @param  \Illuminate\Http\Request  $request  Requisição HTTP recebida.
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next  Próximo middleware.
     * @param  string  $role  Slug da role exigida.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user()?->hasRole($role)) {
            abort(403, 'Você não tem permissão para acessar esta página.');
        }

        return $next($request);
    }
}