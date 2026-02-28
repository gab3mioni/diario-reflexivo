<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonResponse;
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
        return User::with([
            'subjectsAsStudent' => function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            }
        ])->find($studentId);
    }

    /**
     * Get a student's lessons (with responses) scoped to a teacher's subjects.
     *
     * @param User $teacher
     * @param int $studentId
     * @return array{pending: array, answered: array, upcoming: array}
     */
    public function getStudentLessonsForTeacher(User $teacher, int $studentId): array
    {
        $subjectIds = $teacher->subjectsAsTeacher()->pluck('id');

        $lessons = Lesson::whereIn('subject_id', $subjectIds)
            ->where('is_active', true)
            ->with('subject:id,name')
            ->orderBy('scheduled_at', 'desc')
            ->get();

        $responses = LessonResponse::where('student_id', $studentId)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->get()
            ->keyBy('lesson_id');

        $now = now();
        $pending = [];
        $answered = [];
        $upcoming = [];

        foreach ($lessons as $lesson) {
            $response = $responses->get($lesson->id);
            $isAvailable = $lesson->scheduled_at->lte($now);

            $item = [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'scheduled_at' => $lesson->scheduled_at->toISOString(),
                'is_available' => $isAvailable,
                'subject' => [
                    'id' => $lesson->subject->id,
                    'name' => $lesson->subject->name,
                ],
                'response' => $response ? [
                    'id' => $response->id,
                    'content' => $response->content,
                    'submitted_at' => $response->submitted_at?->toISOString(),
                ] : null,
            ];

            if ($response) {
                $answered[] = $item;
            } elseif ($isAvailable) {
                $pending[] = $item;
            } else {
                $upcoming[] = $item;
            }
        }

        return compact('pending', 'answered', 'upcoming');
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