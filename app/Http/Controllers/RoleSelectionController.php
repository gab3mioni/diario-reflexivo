<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleSelectionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RoleSelectionController extends Controller
{
    /**
     * Store the selected role in session.
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
