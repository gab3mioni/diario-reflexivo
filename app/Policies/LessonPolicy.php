<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Lesson $lesson): bool
    {
        return $this->isTeacherOfLesson($user, $lesson)
            || $this->isStudentOfLesson($user, $lesson);
    }

    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $this->isTeacherOfLesson($user, $lesson);
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $this->isTeacherOfLesson($user, $lesson);
    }

    private function isTeacherOfLesson(User $user, Lesson $lesson): bool
    {
        if (! $user->isTeacher()) {
            return false;
        }

        return (int) $lesson->subject?->teacher_id === (int) $user->id;
    }

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
