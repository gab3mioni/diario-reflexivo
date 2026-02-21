<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class TeacherStudentsService
{
    /**
     * Get unique students with their subjects for a teacher.
     * 
     * This method uses a more efficient approach by leveraging Laravel's
     * collection methods and avoiding nested loops when possible.
     *
     * @param User $teacher
     * @return Collection
     */
    public function getStudentsWithSubjects(User $teacher): Collection
    {
        $subjects = $teacher->subjectsAsTeacher()
            ->with('students')
            ->get();

        return $subjects
            ->flatMap(function ($subject) {
                return $subject->students->map(function ($student) use ($subject) {
                    return [
                        'student' => $student,
                        'subject' => $subject,
                    ];
                });
            })
            ->groupBy('student.id')
            ->map(function ($group) {
                $student = $group->first()['student'];
                $subjects = $group->pluck('subject');
                
                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'subjects' => $subjects,
                ];
            })
            ->sortBy('name')
            ->values();
    }

    /**
     * Check if a teacher has access to a specific student.
     *
     * @param User $teacher
     * @param int $studentId
     * @return bool
     */
    public function teacherHasAccessToStudent(User $teacher, int $studentId): bool
    {
        return $teacher->subjectsAsTeacher()
            ->whereHas('students', function ($query) use ($studentId) {
                $query->where('users.id', $studentId);
            })
            ->exists();
    }

    /**
     * Get a student with their subjects for a specific teacher.
     *
     * @param User $teacher
     * @param int $studentId
     * @return User|null
     */
    public function getStudentForTeacher(User $teacher, int $studentId): ?User
    {
        return User::with(['subjectsAsStudent' => function ($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id);
        }])->find($studentId);
    }

    /**
     * Sync a student's subjects within a teacher's scope.
     *
     * @param User $teacher
     * @param User $student
     * @param array $subjectIds
     * @return void
     */
    public function syncStudentSubjects(User $teacher, User $student, array $subjectIds): void
    {
        $teacherSubjectIds = $teacher->subjectsAsTeacher()
            ->pluck('id')
            ->toArray();

        $validSubjectIds = array_intersect($subjectIds, $teacherSubjectIds);

        $student->subjectsAsStudent()->sync($validSubjectIds);
    }
}