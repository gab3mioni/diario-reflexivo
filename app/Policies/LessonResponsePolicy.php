<?php

namespace App\Policies;

use App\Models\LessonResponse;
use App\Models\User;

/**
 * Política de autorização para o modelo LessonResponse.
 */
class LessonResponsePolicy
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
     * Verifica se o usuário pode visualizar a resposta (aluno dono ou professor da disciplina).
     *
     * @param  User            $user      Usuário autenticado.
     * @param  LessonResponse  $response  Resposta de aula.
     * @return bool
     */
    public function view(User $user, LessonResponse $response): bool
    {
        return $this->isOwnerStudent($user, $response)
            || $this->isSubjectTeacher($user, $response);
    }

    /**
     * Verifica se o usuário pode interagir com a resposta (aluno dono e ainda não submetida).
     *
     * @param  User            $user      Usuário autenticado.
     * @param  LessonResponse  $response  Resposta de aula.
     * @return bool
     */
    public function interact(User $user, LessonResponse $response): bool
    {
        if (! $this->isOwnerStudent($user, $response)) {
            return false;
        }

        return $response->submitted_at === null;
    }

    /**
     * Verifica se o usuário pode solicitar análise da resposta (professor da disciplina).
     *
     * @param  User            $user      Usuário autenticado.
     * @param  LessonResponse  $response  Resposta de aula.
     * @return bool
     */
    public function requestAnalysis(User $user, LessonResponse $response): bool
    {
        return $this->isSubjectTeacher($user, $response);
    }

    /**
     * Verifica se o usuário pode revisar a análise da resposta (professor da disciplina).
     *
     * @param  User            $user      Usuário autenticado.
     * @param  LessonResponse  $response  Resposta de aula.
     * @return bool
     */
    public function reviewAnalysis(User $user, LessonResponse $response): bool
    {
        return $this->isSubjectTeacher($user, $response);
    }

    /**
     * Verifica se o usuário é o aluno que criou a resposta.
     *
     * @param  User            $user      Usuário autenticado.
     * @param  LessonResponse  $response  Resposta de aula.
     * @return bool
     */
    private function isOwnerStudent(User $user, LessonResponse $response): bool
    {
        return $user->isStudent()
            && (int) $response->student_id === (int) $user->id;
    }

    /**
     * Verifica se o usuário é o professor da disciplina da resposta.
     *
     * @param  User            $user      Usuário autenticado.
     * @param  LessonResponse  $response  Resposta de aula.
     * @return bool
     */
    private function isSubjectTeacher(User $user, LessonResponse $response): bool
    {
        if (! $user->isTeacher()) {
            return false;
        }

        return (int) ($response->lesson?->subject?->teacher_id ?? 0) === (int) $user->id;
    }
}
