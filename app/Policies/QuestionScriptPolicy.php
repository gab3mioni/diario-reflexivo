<?php

namespace App\Policies;

use App\Models\QuestionScript;
use App\Models\User;

/**
 * Política de autorização para o modelo QuestionScript (exclusivo para administradores).
 */
class QuestionScriptPolicy
{
    /**
     * Concede acesso a administradores e nega para demais usuários.
     *
     * @param  User    $user     Usuário autenticado.
     * @param  string  $ability  Habilidade sendo verificada.
     * @return ?bool
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : false;
    }

    /**
     * Verifica se o usuário pode listar roteiros de perguntas.
     *
     * @param  User  $user  Usuário autenticado.
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Verifica se o usuário pode visualizar um roteiro de perguntas.
     *
     * @param  User            $user    Usuário autenticado.
     * @param  QuestionScript  $script  Roteiro de perguntas.
     * @return bool
     */
    public function view(User $user, QuestionScript $script): bool
    {
        return false;
    }

    /**
     * Verifica se o usuário pode atualizar um roteiro de perguntas.
     *
     * @param  User            $user    Usuário autenticado.
     * @param  QuestionScript  $script  Roteiro de perguntas.
     * @return bool
     */
    public function update(User $user, QuestionScript $script): bool
    {
        return false;
    }

    /**
     * Verifica se o usuário pode ativar/desativar um roteiro de perguntas.
     *
     * @param  User            $user    Usuário autenticado.
     * @param  QuestionScript  $script  Roteiro de perguntas.
     * @return bool
     */
    public function toggleActive(User $user, QuestionScript $script): bool
    {
        return false;
    }
}
