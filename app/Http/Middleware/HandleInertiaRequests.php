<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Middleware que configura os dados partilhados e a versão de assets do Inertia.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * Template raiz carregado na primeira visita.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determina a versão atual dos assets para cache-busting.
     *
     * @see https://inertiajs.com/asset-versioning
     *
     * @param  \Illuminate\Http\Request  $request  Requisição HTTP recebida.
     * @return string|null
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define as props partilhadas por padrão em todas as páginas Inertia.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @param  \Illuminate\Http\Request  $request  Requisição HTTP recebida.
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => $user ? $user->roles()->select('roles.id', 'roles.slug', 'roles.display_name')->get() : null,
                'selectedRole' => session('selected_role'),
                'hasMultipleRoles' => $user && $user->roles()->count() > 1,
                'unread_notifications_count' => $user?->unreadNotifications()->count() ?? 0,
                'recent_notifications' => $user
                    ? $user->unreadNotifications()->take(10)->get()->map(fn ($n) => [
                        'id' => $n->id,
                        'type' => $n->type,
                        'data' => $n->data,
                        'created_at' => $n->created_at?->toISOString(),
                    ])
                    : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
