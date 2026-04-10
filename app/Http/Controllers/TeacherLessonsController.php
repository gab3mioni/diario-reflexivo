<?php

namespace App\Http\Controllers;

use App\Exceptions\AiProviderException;
use App\Models\DiaryAnalysis;
use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Services\DiaryAnalysisService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Response as InertiaResponse;
use Throwable;

class TeacherLessonsController extends Controller
{
    /**
     * List all lessons for the teacher's subjects.
     */
    public function index(Request $request): InertiaResponse
    {
        $teacher = Auth::user();

        $subjectId = $request->query('subject_id');

        $lessons = Lesson::whereIn('subject_id', $teacher->subjectsAsTeacher()->pluck('id'))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->with(['subject' => fn ($q) => $q->withCount('students')])
            ->withCount('responses')
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
                    'responses_count' => $lesson->responses_count,
                    'students_count' => $lesson->subject->students_count ?? 0,
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
    public function store(Request $request): RedirectResponse
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

        return redirect()->route('lessons.index')
            ->with('success', 'Aula criada com sucesso!');
    }

    /**
     * Store lessons in bulk based on a day-of-week pattern within a date range.
     */
    public function storeBulk(Request $request): RedirectResponse
    {
        $teacher = Auth::user();

        $validated = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'title_prefix' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'day_of_week' => 'required|integer|min:1|max:6',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i|after_or_equal:06:00',
        ], [
            'day_of_week.min' => 'Aulas aos domingos não são permitidas.',
            'start_time.after_or_equal' => 'Horários entre 00:00 e 05:59 não são permitidos.',
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
                'title' => $validated['title_prefix'].' '.$counter,
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
            return redirect()->route('lessons.index')
                ->with('error', 'Nenhuma aula foi gerada para o período selecionado.');
        }

        Lesson::insert($lessons);

        return redirect()->route('lessons.index')
            ->with('success', count($lessons).' aulas criadas com sucesso!');
    }

    /**
     * Show a specific lesson with student responses.
     */
    public function show($lessonId): InertiaResponse
    {
        $lesson = Lesson::with(['subject.students', 'responses.student', 'responses.diaryAnalyses', 'responses.alerts'])
            ->findOrFail($lessonId);

        $this->authorize('view', $lesson);

        $students = $lesson->subject->students;
        $responses = $lesson->responses;

        $studentData = $students->map(function ($student) use ($responses) {
            $response = $responses->firstWhere('student_id', $student->id);

            $latestAnalysis = $response?->diaryAnalyses->first();
            $unreadAlerts = $response
                ? $response->alerts->whereNull('read_at')
                : collect();
            $highestSeverity = $this->highestSeverity($unreadAlerts);

            return [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'responded' => $response !== null && $response->submitted_at !== null,
                'response' => $response ? [
                    'id' => $response->id,
                    'content' => $response->content,
                    'submitted_at' => $response->submitted_at?->toISOString(),
                    'latest_analysis_status' => $latestAnalysis?->status,
                    'unread_alerts_count' => $unreadAlerts->count(),
                    'highest_alert_severity' => $highestSeverity,
                    'alert_types' => $unreadAlerts->pluck('type')->unique()->values()->all(),
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
     * Pick the highest severity across a collection of alerts.
     * Order: high > medium > low.
     */
    private function highestSeverity(\Illuminate\Support\Collection $alerts): ?string
    {
        if ($alerts->isEmpty()) {
            return null;
        }
        if ($alerts->contains('severity', 'high')) {
            return 'high';
        }
        if ($alerts->contains('severity', 'medium')) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Show the form to edit a lesson.
     */
    public function edit($lessonId): InertiaResponse
    {
        $teacher = Auth::user();

        $lesson = Lesson::findOrFail($lessonId);
        $this->authorize('update', $lesson);

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
    public function update(Request $request, $lessonId): RedirectResponse
    {
        $lesson = Lesson::findOrFail($lessonId);
        $this->authorize('update', $lesson);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'scheduled_at' => 'required|date',
            'is_active' => 'boolean',
        ]);

        $lesson->update($validated);

        return redirect()->route('lessons.show', $lesson->id)
            ->with('success', 'Aula atualizada com sucesso!');
    }

    /**
     * Delete a lesson.
     */
    public function destroy($lessonId): RedirectResponse
    {
        $lesson = Lesson::findOrFail($lessonId);
        $this->authorize('delete', $lesson);

        $lesson->delete();

        return redirect()->route('lessons.index')
            ->with('success', 'Aula removida com sucesso!');
    }

    /**
     * Show the analysis detail page for a specific student response.
     */
    public function showAnalysis($responseId): InertiaResponse
    {
        $response = LessonResponse::with(['student', 'chatMessages', 'lesson.subject'])
            ->findOrFail($responseId);

        $this->authorize('view', $response);

        $lesson = $response->lesson;

        $analyses = DiaryAnalysis::where('lesson_response_id', $response->id)
            ->with(['promptVersion', 'providerConfig'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DiaryAnalysis $a) => [
                'id' => $a->id,
                'lesson_response_id' => $a->lesson_response_id,
                'status' => $a->status,
                'result' => $a->result,
                'error_message' => $a->error_message,
                'teacher_notes' => $a->teacher_notes,
                'reviewed_by' => $a->reviewed_by,
                'reviewed_at' => $a->reviewed_at?->toISOString(),
                'prompt_version' => $a->promptVersion->version,
                'provider_name' => $a->providerConfig->provider,
                'model_name' => $a->providerConfig->model,
                'created_at' => $a->created_at->toISOString(),
            ]);

        $service = app(DiaryAnalysisService::class);

        return inertia('teacher/lessons/analysis', [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'subject' => [
                    'id' => $lesson->subject->id,
                    'name' => $lesson->subject->name,
                ],
            ],
            'student' => [
                'id' => $response->student->id,
                'name' => $response->student->name,
                'email' => $response->student->email,
            ],
            'response' => [
                'id' => $response->id,
                'content' => $response->content,
                'submitted_at' => $response->submitted_at->toISOString(),
            ],
            'chatMessages' => $response->chatMessages->map(fn ($msg) => [
                'id' => $msg->id,
                'node_id' => $msg->node_id,
                'role' => $msg->role,
                'content' => $msg->content,
                'created_at' => $msg->created_at->toISOString(),
            ]),
            'analyses' => $analyses,
            'canReanalyze' => $service->canRequestAnalysis($response->id),
        ]);
    }

    /**
     * Request a new AI analysis for a student response.
     */
    public function requestAnalysis($responseId): RedirectResponse
    {
        $response = LessonResponse::with('lesson.subject')->findOrFail($responseId);
        $this->authorize('requestAnalysis', $response);

        $service = app(DiaryAnalysisService::class);

        try {
            $service->requestAnalysis($response);

            return redirect()->route('diaries.show', $response->id)
                ->with('success', 'Análise solicitada com sucesso!');
        } catch (AiProviderException $e) {
            Log::warning('Diary analysis request failed', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('diaries.show', $response->id)
                ->with('error', 'Não foi possível solicitar a análise da IA no momento. Tente novamente.');
        } catch (Throwable $e) {
            Log::error('Unexpected error while requesting diary analysis', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('diaries.show', $response->id)
                ->with('error', 'Ocorreu um erro inesperado ao solicitar a análise. Tente novamente.');
        }
    }

    /**
     * Approve or reject an AI analysis (HITL review).
     */
    public function reviewAnalysis(Request $request, $responseId, $analysisId): RedirectResponse
    {
        $teacher = Auth::user();

        $response = LessonResponse::with('lesson.subject')->findOrFail($responseId);
        $this->authorize('reviewAnalysis', $response);

        $analysis = DiaryAnalysis::where('id', $analysisId)
            ->where('lesson_response_id', $response->id)
            ->firstOrFail();

        $validated = $request->validate([
            'action' => 'required|string|in:approved,rejected',
            'notes' => 'nullable|string|max:2000',
        ]);

        $service = app(DiaryAnalysisService::class);

        if ($validated['action'] === 'approved') {
            $service->approveAnalysis($analysis, $teacher, $validated['notes']);
        } else {
            $service->rejectAnalysis($analysis, $teacher, $validated['notes']);
        }

        return redirect()->route('diaries.show', $response->id)
            ->with('success', 'Análise revisada com sucesso!');
    }
}
