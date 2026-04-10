<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * Controlador responsável pela gestão da autenticação de dois fatores.
 */
class TwoFactorAuthenticationController extends Controller implements HasMiddleware
{
    /**
     * Obtém os middlewares que devem ser atribuídos ao controlador.
     *
     * @return array<int, \Illuminate\Routing\Controllers\Middleware|string>
     */
    public static function middleware(): array
    {
        return Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
            ? [new Middleware('password.confirm', only: ['show'])]
            : [];
    }

    /**
     * Exibe a página de configurações de autenticação de dois fatores do utilizador.
     *
     * @param  \App\Http\Requests\Settings\TwoFactorAuthenticationRequest  $request
     * @return \Inertia\Response
     */
    public function show(TwoFactorAuthenticationRequest $request): Response
    {
        $request->ensureStateIsValid();

        return Inertia::render('settings/two-factor', [
            'twoFactorEnabled' => $request->user()->hasEnabledTwoFactorAuthentication(),
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
        ]);
    }
}
