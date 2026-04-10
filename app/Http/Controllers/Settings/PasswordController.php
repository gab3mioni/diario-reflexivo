<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador responsável pela gestão da senha do utilizador.
 */
class PasswordController extends Controller
{
    /**
     * Exibe a página de configurações de senha do utilizador.
     *
     * @return \Inertia\Response
     */
    public function edit(): Response
    {
        return Inertia::render('settings/password');
    }

    /**
     * Atualiza a senha do utilizador.
     *
     * @param  \App\Http\Requests\Settings\PasswordUpdateRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return back()->with('success', 'Senha atualizada com sucesso.');
    }
}
