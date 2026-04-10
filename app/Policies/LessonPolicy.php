<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

/**
 * Política de autorização para o modelo Lesson.
 */
class LessonPolicy
{
    /**
     * Concede acesso total a administradores antes de qualquer verificação.
     *
     * @param  User    $user     Usuário autenticado.
     * @param  string  $ability  Habilidade sendo verificada.
     * @return ?bool  True para admins, null para continuar a verificação.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Verifica se o usuário pode visualizar a aula (professor da disciplina ou aluno matriculado).
     *
     * @param  User    $user    Usuário autenticado.
     * @param  Lesson  $lesson  Aula a ser visualizada.
     * @return bool
     */
    public function view(User $user, Lesson $lesson): bool
    {
        return $this->isTeacherOfLesson($user, $lesson)
            || $this->isStudentOfLesson($user, $lesson);
    }

    /**
     * Verifica se o usuário pode criar aulas (apenas professores).
     *
     * @param  User  $user  Usuário autenticado.
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    /**
     * Verifica se o usuário pode atualizar a aula (professor da disciplina).
     *
     * @param  User    $user    Usuário autenticado.
     * @param  Lesson  $lesson  Aula a ser atualizada.
     * @return bool
     */
    public function update(User $user, Lesson $lesson): bool
    {
        return $this->isTeacherOfLesson($user, $lesson);
    }

    /**
     * Verifica se o usuário pode excluir a aula (professor da disciplina).
     *
     * @param  User    $user    Usuário autenticado.
     * @param  Lesson  $lesson  Aula a ser excluída.
     * @return bool
     */
    public function delete(User $user, Lesson $lesson): bool
    {
        return $this->isTeacherOfLesson($user, $lesson);
    }

    /**
     * Verifica se o usuário é o professor da disciplina à qual a aula pertence.
     *
     * @param  User    $user    Usuário autenticado.
     * @param  Lesson  $lesson  Aula.
     * @return bool
     */
    private function isTeacherOfLesson(User $user, Lesson $lesson): bool
    {
        if (! $user->isTeacher()) {
            return false;
        }

        return (int) $lesson->subject?->teacher_id === (int) $user->id;
    }

    /**
     * Verifica se o usuário é aluno matriculado na disciplina da aula.
     *
     * @param  User    $user    Usuário autenticado.
     * @param  Lesson  $lesson  Aula.
     * @return bool
     */
    private function isStudentOfLesson(User $user, Lesson $lesson): bool
    {
        if (! $user->isStudent()) {
            return false;
        }

        return $lesson->subject
            ?->students()
            ->whereKey($user->id)
            ->exists() ?? false;
    }
}
