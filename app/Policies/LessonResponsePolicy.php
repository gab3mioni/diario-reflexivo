<?php

namespace App\Policies;

use App\Models\LessonResponse;
use App\Models\User;

class LessonResponsePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, LessonResponse $response): bool
    {
        return $this->isOwnerStudent($user, $response)
            || $this->isSubjectTeacher($user, $response);
    }

    public function interact(User $user, LessonResponse $response): bool
    {
        if (! $this->isOwnerStudent($user, $response)) {
            return false;
        }

        return $response->submitted_at === null;
    }

    public function requestAnalysis(User $user, LessonResponse $response): bool
    {
        return $this->isSubjectTeacher($user, $response);
    }

    public function reviewAnalysis(User $user, LessonResponse $response): bool
    {
        return $this->isSubjectTeacher($user, $response);
    }

    private function isOwnerStudent(User $user, LessonResponse $response): bool
    {
        return $user->isStudent()
            && (int) $response->student_id === (int) $user->id;
    }

    private function isSubjectTeacher(User $user, LessonResponse $response): bool
    {
        if (! $user->isTeacher()) {
            return false;
        }

        return (int) ($response->lesson?->subject?->teacher_id ?? 0) === (int) $user->id;
    }
}
