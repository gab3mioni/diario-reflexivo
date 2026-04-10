<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redireciona admin/teacher para a página de settings se 2FA ainda não foi configurado.
 *
 * Aplica-se apenas a usuários cujo papel exige 2FA obrigatório (admin, teacher).
 * Estudantes não são afetados. Se o usuário já tem `two_factor_confirmed_at`
 * preenchido, o middleware é um no-op.
 *
 * Rotas de settings e de API não são interceptadas para evitar loop de redirect.
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Apenas admin/teacher precisam de 2FA obrigatório.
        if (! $user->isAdmin() && ! $user->isTeacher()) {
            return $next($request);
        }

        // Já configurou 2FA.
        if ($user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        // Permitir acesso às rotas de configuração para que o usuário possa
        // configurar o 2FA, e às rotas de logout / two-factor.
        if ($request->routeIs('settings.*', 'logout', 'two-factor.*', 'user-password.*', 'password.*')) {
            return $next($request);
        }

        return redirect()
            ->route('settings.edit')
            ->with('error', 'Para continuar, ative a autenticação em dois fatores nas configurações da conta.');
    }
}
