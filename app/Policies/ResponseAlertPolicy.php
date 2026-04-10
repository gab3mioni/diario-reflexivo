<?php

namespace App\Policies;

use App\Models\ResponseAlert;
use App\Models\User;

/**
 * Política de autorização para o modelo ResponseAlert.
 */
class ResponseAlertPolicy
{
    /**
     * Concede acesso total a administradores antes de qualquer verificação.
     *
     * @param  User    $user     Usuário autenticado.
     * @param  string  $ability  Habilidade sendo verificada.
     * @return ?bool
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Verifica se o usuário pode visualizar o alerta (professor da disciplina).
     *
     * @param  User           $user   Usuário autenticado.
     * @param  ResponseAlert  $alert  Alerta.
     * @return bool
     */
    public function view(User $user, ResponseAlert $alert): bool
    {
        return $this->isSubjectTeacher($user, $alert);
    }

    /**
     * Verifica se o usuário pode marcar o alerta como lido (professor da disciplina).
     *
     * @param  User           $user   Usuário autenticado.
     * @param  ResponseAlert  $alert  Alerta.
     * @return bool
     */
    public function markAsRead(User $user, ResponseAlert $alert): bool
    {
        return $this->isSubjectTeacher($user, $alert);
    }

    /**
     * Verifica se o usuário é o professor da disciplina associada ao alerta.
     *
     * @param  User           $user   Usuário autenticado.
     * @param  ResponseAlert  $alert  Alerta.
     * @return bool
     */
    private function isSubjectTeacher(User $user, ResponseAlert $alert): bool
    {
        if (! $user->isTeacher()) {
            return false;
        }

        $teacherId = $alert->lessonResponse?->lesson?->subject?->teacher_id;

        return $teacherId !== null && (int) $teacherId === (int) $user->id;
    }
}
