<?php

namespace App\Policies;

use App\Models\ResponseAlert;
use App\Models\User;

class ResponseAlertPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Ver / marcar como lido: apenas professor da subject dona da resposta.
     * Alunos NÃO devem enxergar alertas sobre si mesmos.
     */
    public function view(User $user, ResponseAlert $alert): bool
    {
        return $this->isSubjectTeacher($user, $alert);
    }

    public function markAsRead(User $user, ResponseAlert $alert): bool
    {
        return $this->isSubjectTeacher($user, $alert);
    }

    private function isSubjectTeacher(User $user, ResponseAlert $alert): bool
    {
        if (! $user->isTeacher()) {
            return false;
        }

        $teacherId = $alert->lessonResponse?->lesson?->subject?->teacher_id;

        return $teacherId !== null && (int) $teacherId === (int) $user->id;
    }
}
