<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\QuestionScript;
use App\Services\DiaryAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $inProgress = [];
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

            if (! $isAvailable) {
                $upcoming[] = $lessonData;
            } elseif ($response && $response->submitted_at) {
                $answered[] = $lessonData;
            } elseif ($response && $response->chatMessages()->exists()) {
                $inProgress[] = $lessonData;
            } else {
                $pending[] = $lessonData;
            }
        }

        return inertia('student/lessons/index', [
            'pending' => $pending,
            'inProgress' => $inProgress,
            'answered' => $answered,
            'upcoming' => $upcoming,
        ]);
    }

    /**
     * Show the diary chat for a specific lesson.
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
            ->with('chatMessages')
            ->first();

        // Get the active question script
        $script = QuestionScript::active();
        $questionCount = 0;
        if ($script) {
            $questionCount = collect($script->getOrderedNodes())
                ->where('type', 'question')
                ->count();
        }

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
            'chatMessages' => $response ? $response->chatMessages->map(fn ($msg) => [
                'id' => $msg->id,
                'node_id' => $msg->node_id,
                'role' => $msg->role,
                'content' => $msg->content,
                'created_at' => $msg->created_at->toISOString(),
            ]) : [],
            'currentNodeId' => $response ? $this->getCurrentNodeId($response) : null,
            'totalQuestions' => $questionCount,
            'draft' => Cache::get($this->draftCacheKey($lesson->id, $student->id), ''),
        ]);
    }

    /**
     * Start a new chat session for a lesson.
     */
    public function startChat($lessonId)
    {
        $student = Auth::user();
        $subjectIds = $student->subjectsAsStudent()->pluck('subjects.id');

        $lesson = Lesson::whereIn('subject_id', $subjectIds)
            ->where('is_active', true)
            ->findOrFail($lessonId);

        if ($lesson->isFuture()) {
            abort(403, 'Aula não disponível.');
        }

        $response = LessonResponse::firstOrCreate(
            ['lesson_id' => $lesson->id, 'student_id' => $student->id],
            ['content' => '']
        );

        $lock = Cache::lock("chat_start:{$response->id}", 10);

        if (! $lock->get()) {
            return redirect()->back();
        }

        try {
            if ($response->chatMessages()->exists()) {
                return redirect()->back();
            }

            $script = QuestionScript::active();
            if (! $script) {
                abort(422, 'Roteiro não configurado.');
            }

            $orderedNodes = $script->getOrderedNodes();
            $startNode = collect($orderedNodes)->firstWhere('type', 'start');
            $firstQuestion = collect($orderedNodes)->firstWhere('type', 'question');

            if ($startNode) {
                ChatMessage::create([
                    'lesson_response_id' => $response->id,
                    'node_id' => $startNode['id'],
                    'role' => 'bot',
                    'content' => $startNode['data']['message'],
                ]);
            }

            if ($firstQuestion) {
                ChatMessage::create([
                    'lesson_response_id' => $response->id,
                    'node_id' => $firstQuestion['id'],
                    'role' => 'bot',
                    'content' => $firstQuestion['data']['message'],
                ]);
            }
        } finally {
            $lock->release();
        }

        return redirect()->back();
    }

    /**
     * Send a chat message (student response to a question).
     */
    public function sendMessage(Request $request, $lessonId)
    {
        $student = Auth::user();
        $subjectIds = $student->subjectsAsStudent()->pluck('subjects.id');

        $lesson = Lesson::whereIn('subject_id', $subjectIds)
            ->where('is_active', true)
            ->findOrFail($lessonId);

        if ($lesson->isFuture()) {
            abort(403, 'Aula não disponível.');
        }

        $validated = $request->validate([
            'content' => 'required|string|max:10000',
            'node_id' => 'required|string',
        ]);

        $response = LessonResponse::firstOrCreate(
            ['lesson_id' => $lesson->id, 'student_id' => $student->id],
            ['content' => '']
        );

        if ($response->submitted_at) {
            abort(403, 'Já finalizado.');
        }

        // Save student message
        ChatMessage::create([
            'lesson_response_id' => $response->id,
            'node_id' => $validated['node_id'],
            'role' => 'student',
            'content' => $validated['content'],
        ]);

        Cache::forget($this->draftCacheKey($lesson->id, $student->id));

        // Determine next node from QuestionScript
        $script = QuestionScript::active();
        if (! $script) {
            abort(422, 'Roteiro não configurado.');
        }

        $orderedNodes = $script->getOrderedNodes();
        $currentNodeIndex = collect($orderedNodes)->search(fn ($n) => $n['id'] === $validated['node_id']);

        if ($currentNodeIndex === false) {
            abort(422, 'Nó não encontrado.');
        }

        $nextNode = $orderedNodes[$currentNodeIndex + 1] ?? null;

        if ($nextNode && $nextNode['type'] === 'end') {
            ChatMessage::create([
                'lesson_response_id' => $response->id,
                'node_id' => $nextNode['id'],
                'role' => 'bot',
                'content' => $nextNode['data']['message'],
            ]);

            $this->consolidateResponse($response);
        } elseif ($nextNode && $nextNode['type'] === 'question') {
            ChatMessage::create([
                'lesson_response_id' => $response->id,
                'node_id' => $nextNode['id'],
                'role' => 'bot',
                'content' => $nextNode['data']['message'],
            ]);
        }

        return redirect()->back();
    }

    /**
     * Consolidate all chat messages into the lesson response content.
     */
    private function consolidateResponse(LessonResponse $response): void
    {
        $studentMessages = $response->chatMessages()
            ->where('role', 'student')
            ->get();

        $botMessages = $response->chatMessages()
            ->where('role', 'bot')
            ->get()
            ->keyBy('node_id');

        $consolidatedParts = [];
        foreach ($studentMessages as $msg) {
            $botQuestion = $botMessages->get($msg->node_id);
            if ($botQuestion) {
                $consolidatedParts[] = "**{$botQuestion->content}**\n{$msg->content}";
            } else {
                $consolidatedParts[] = $msg->content;
            }
        }

        $response->update([
            'content' => implode("\n\n", $consolidatedParts),
            'submitted_at' => now(),
        ]);

        try {
            app(DiaryAnalysisService::class)->requestAnalysis($response);
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch diary analysis: '.$e->getMessage());
        }
    }

    /**
     * Get the current node ID awaiting a student response.
     */
    private function getCurrentNodeId(LessonResponse $response): ?string
    {
        $script = QuestionScript::active();
        if (! $script) {
            return null;
        }

        $questionNodeIds = collect($script->getOrderedNodes())
            ->where('type', 'question')
            ->pluck('id');

        $answeredNodeIds = $response->chatMessages()
            ->where('role', 'student')
            ->pluck('node_id');

        return $questionNodeIds->diff($answeredNodeIds)->first();
    }

    private function draftCacheKey(int $lessonId, int $studentId): string
    {
        return "chat_draft:{$studentId}:{$lessonId}";
    }

    public function saveDraft(Request $request, $lessonId)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        $student = Auth::user();
        Cache::put(
            $this->draftCacheKey($lessonId, $student->id),
            $validated['content'],
            now()->addHours(24),
        );

        return redirect()->back();
    }
}
