<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador responsável pela gestão do perfil do utilizador.
 */
class ProfileController extends Controller
{
    /**
     * Exibe a página de configurações de perfil do utilizador.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'isStudent' => $request->user()->isStudent() && session('selected_role') === 'student',
        ]);
    }

    /**
     * Atualiza as informações de perfil do utilizador.
     *
     * @param  \App\Http\Requests\Settings\ProfileUpdateRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Students can only update email, not name
        if ($request->user()->isStudent() && session('selected_role') === 'student') {
            unset($validated['name']);
        }

        $request->user()->fill($validated);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit')
            ->with('success', 'Perfil atualizado com sucesso.');
    }

    /**
     * Elimina a conta do utilizador.
     *
     * @param  \App\Http\Requests\Settings\ProfileDeleteRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Students cannot delete their account
        if ($user->isStudent() && session('selected_role') === 'student') {
            abort(403);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
