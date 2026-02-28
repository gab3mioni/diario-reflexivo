<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentLessonsController extends Controller
{
    /**
     * List all lessons for the student, grouped by status.
     */
    public function index()
    {
        $student = Auth::user();

        // Get all subject IDs the student is enrolled in
        $subjectIds = $student->subjectsAsStudent()->pluck('subjects.id');

        $lessons = Lesson::whereIn('subject_id', $subjectIds)
            ->where('is_active', true)
            ->with('subject')
            ->orderBy('scheduled_at', 'desc')
            ->get();

        // Get all responses for this student
        $responses = LessonResponse::where('student_id', $student->id)
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

            $lessonData = [
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

            if (!$isAvailable) {
                $upcoming[] = $lessonData;
            } elseif ($response) {
                $answered[] = $lessonData;
            } else {
                $pending[] = $lessonData;
            }
        }

        return inertia('student/lessons/index', [
            'pending' => $pending,
            'answered' => $answered,
            'upcoming' => $upcoming,
        ]);
    }

    /**
     * Show the diary form for a specific lesson.
     */
    public function show($lessonId)
    {
        $student = Auth::user();

        $subjectIds = $student->subjectsAsStudent()->pluck('subjects.id');

        $lesson = Lesson::whereIn('subject_id', $subjectIds)
            ->where('is_active', true)
            ->with('subject')
            ->findOrFail($lessonId);

        $response = LessonResponse::where('lesson_id', $lesson->id)
            ->where('student_id', $student->id)
            ->first();

        return inertia('student/lessons/show', [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'scheduled_at' => $lesson->scheduled_at->toISOString(),
                'is_available' => $lesson->isAvailable(),
                'subject' => [
                    'id' => $lesson->subject->id,
                    'name' => $lesson->subject->name,
                ],
            ],
            'response' => $response ? [
                'id' => $response->id,
                'content' => $response->content,
                'submitted_at' => $response->submitted_at?->toISOString(),
            ] : null,
        ]);
    }

    /**
     * Submit or update a diary response for a lesson.
     */
    public function store(Request $request, $lessonId)
    {
        $student = Auth::user();

        $subjectIds = $student->subjectsAsStudent()->pluck('subjects.id');

        $lesson = Lesson::whereIn('subject_id', $subjectIds)
            ->where('is_active', true)
            ->findOrFail($lessonId);

        // Ensure the lesson is available (not in the future)
        if ($lesson->isFuture()) {
            abort(403, 'Esta aula ainda não está disponível para resposta.');
        }

        // Ensure the student hasn't already responded
        $existingResponse = LessonResponse::where('lesson_id', $lesson->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existingResponse) {
            abort(403, 'Você já respondeu a esta aula. Não é possível alterar a resposta.');
        }

        $validated = $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        $response = LessonResponse::create([
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'content' => $validated['content'],
            'submitted_at' => now(),
        ]);

        return redirect()->route('student.lessons.show', $lesson->id)
            ->with('success', 'Diário enviado com sucesso!');
    }
}
