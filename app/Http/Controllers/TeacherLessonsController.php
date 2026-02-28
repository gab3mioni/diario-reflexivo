<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherLessonsController extends Controller
{
    /**
     * List all lessons for the teacher's subjects.
     */
    public function index(Request $request)
    {
        $teacher = Auth::user();

        $subjectId = $request->query('subject_id');

        $lessons = Lesson::whereIn('subject_id', $teacher->subjectsAsTeacher()->pluck('id'))
            ->when($subjectId, fn($q) => $q->where('subject_id', $subjectId))
            ->with(['subject', 'responses.student'])
            ->orderBy('scheduled_at', 'desc')
            ->get()
            ->map(function ($lesson) {
                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'description' => $lesson->description,
                    'scheduled_at' => $lesson->scheduled_at->toISOString(),
                    'is_active' => $lesson->is_active,
                    'is_available' => $lesson->isAvailable(),
                    'subject' => [
                        'id' => $lesson->subject->id,
                        'name' => $lesson->subject->name,
                    ],
                    'responses_count' => $lesson->responses->count(),
                    'students_count' => $lesson->subject->students()->count(),
                ];
            });

        $subjects = $teacher->subjectsAsTeacher()
            ->where('is_active', true)
            ->get(['id', 'name']);

        return inertia('teacher/lessons/index', [
            'lessons' => $lessons,
            'subjects' => $subjects,
            'filters' => [
                'subject_id' => $subjectId,
            ],
        ]);
    }

    /**
     * Store a new lesson (single).
     */
    public function store(Request $request)
    {
        $teacher = Auth::user();

        $validated = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'scheduled_at' => 'required|date',
        ]);

        // Ensure the teacher owns this subject
        $subject = $teacher->subjectsAsTeacher()->findOrFail($validated['subject_id']);

        $lesson = Lesson::create([
            'subject_id' => $subject->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'scheduled_at' => $validated['scheduled_at'],
        ]);

        return redirect()->route('teacher.lessons.index')
            ->with('success', 'Aula criada com sucesso!');
    }

    /**
     * Store lessons in bulk based on a day-of-week pattern within a date range.
     */
    public function storeBulk(Request $request)
    {
        $teacher = Auth::user();

        $validated = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'title_prefix' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
        ]);

        $subject = $teacher->subjectsAsTeacher()->findOrFail($validated['subject_id']);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $dayOfWeek = (int) $validated['day_of_week'];

        // Find the first occurrence of the selected day of week
        $current = $startDate->copy();
        if ($current->dayOfWeek !== $dayOfWeek) {
            $current->next($dayOfWeek);
        }

        $lessons = [];
        $counter = 1;

        while ($current->lte($endDate)) {
            $scheduledAt = $current->copy()->setTimeFromTimeString($validated['start_time']);

            $lessons[] = [
                'subject_id' => $subject->id,
                'title' => $validated['title_prefix'] . ' ' . $counter,
                'description' => $validated['description'],
                'scheduled_at' => $scheduledAt,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $counter++;
            $current->addWeek();
        }

        if (empty($lessons)) {
            return redirect()->route('teacher.lessons.index')
                ->with('error', 'Nenhuma aula foi gerada para o período selecionado.');
        }

        Lesson::insert($lessons);

        return redirect()->route('teacher.lessons.index')
            ->with('success', count($lessons) . ' aulas criadas com sucesso!');
    }

    /**
     * Show a specific lesson with student responses.
     */
    public function show($lessonId)
    {
        $teacher = Auth::user();

        $lesson = Lesson::whereIn('subject_id', $teacher->subjectsAsTeacher()->pluck('id'))
            ->with(['subject.students', 'responses.student'])
            ->findOrFail($lessonId);

        $students = $lesson->subject->students;
        $responses = $lesson->responses;

        $studentData = $students->map(function ($student) use ($responses) {
            $response = $responses->firstWhere('student_id', $student->id);

            return [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'responded' => $response !== null,
                'response' => $response ? [
                    'id' => $response->id,
                    'content' => $response->content,
                    'submitted_at' => $response->submitted_at?->toISOString(),
                ] : null,
            ];
        });

        return inertia('teacher/lessons/show', [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'scheduled_at' => $lesson->scheduled_at->toISOString(),
                'is_active' => $lesson->is_active,
                'is_available' => $lesson->isAvailable(),
                'subject' => [
                    'id' => $lesson->subject->id,
                    'name' => $lesson->subject->name,
                ],
            ],
            'students' => $studentData,
        ]);
    }

    /**
     * Show the form to edit a lesson.
     */
    public function edit($lessonId)
    {
        $teacher = Auth::user();

        $lesson = Lesson::whereIn('subject_id', $teacher->subjectsAsTeacher()->pluck('id'))
            ->findOrFail($lessonId);

        $subjects = $teacher->subjectsAsTeacher()
            ->where('is_active', true)
            ->get(['id', 'name']);

        return inertia('teacher/lessons/edit', [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'scheduled_at' => $lesson->scheduled_at->toISOString(),
                'is_active' => $lesson->is_active,
                'subject_id' => $lesson->subject_id,
            ],
            'subjects' => $subjects,
        ]);
    }

    /**
     * Update a lesson.
     */
    public function update(Request $request, $lessonId)
    {
        $teacher = Auth::user();

        $lesson = Lesson::whereIn('subject_id', $teacher->subjectsAsTeacher()->pluck('id'))
            ->findOrFail($lessonId);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'scheduled_at' => 'required|date',
            'is_active' => 'boolean',
        ]);

        $lesson->update($validated);

        return redirect()->route('teacher.lessons.show', $lesson->id)
            ->with('success', 'Aula atualizada com sucesso!');
    }

    /**
     * Delete a lesson.
     */
    public function destroy($lessonId)
    {
        $teacher = Auth::user();

        $lesson = Lesson::whereIn('subject_id', $teacher->subjectsAsTeacher()->pluck('id'))
            ->findOrFail($lessonId);

        $lesson->delete();

        return redirect()->route('teacher.lessons.index')
            ->with('success', 'Aula removida com sucesso!');
    }
}
