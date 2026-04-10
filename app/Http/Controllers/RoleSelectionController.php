<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleSelectionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador de seleção de perfil do usuário.
 */
class RoleSelectionController extends Controller
{
    /**
     * Armazena o perfil selecionado na sessão do usuário.
     *
     * @param  \App\Http\Requests\RoleSelectionRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(RoleSelectionRequest $request): RedirectResponse
    {
        $role = $request->validated('role');

        if (! Auth::user()->hasRole($role)) {
            return back()->withErrors(['role' => 'Você não tem permissão para acessar como esta role.']);
        }

        session(['selected_role' => $role]);

        return redirect()->intended('/dashboard');
    }
}
