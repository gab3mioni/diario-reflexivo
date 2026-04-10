<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que partilha a preferência de aparência com todas as views.
 */
class HandleAppearance
{
    /**
     * Lê o cookie de aparência e partilha o valor com as views.
     *
     * @param  \Illuminate\Http\Request  $request  Requisição HTTP recebida.
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next  Próximo middleware.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');

        return $next($request);
    }
}
