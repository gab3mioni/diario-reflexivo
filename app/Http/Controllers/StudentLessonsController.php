<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessChatTurn;
use App\Models\ChatMessage;
use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\QuestionScript;
use App\Services\Chat\ChatTurnProcessor;
use App\Support\LogContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Response as InertiaResponse;

class StudentLessonsController extends Controller
{
    public function __construct(
        private readonly ChatTurnProcessor $processor,
    ) {}

    /**
     * List all lessons for the student, grouped by status.
     */
    public function index(): InertiaResponse
    {
        $student = Auth::user();

        $subjectIds = $student->subjectsAsStudent()->pluck('subjects.id');

        $lessons = Lesson::whereIn('subject_id', $subjectIds)
            ->where('is_active', true)
            ->with('subject')
            ->orderBy('scheduled_at', 'desc')
            ->get();

        // Carrega respostas com flag has_messages para evitar N+1 no loop abaixo.
        $responses = LessonResponse::where('student_id', $student->id)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->withCount('chatMessages')
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
            } elseif ($response && ($response->chat_messages_count ?? 0) > 0) {
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
    public function show(int $lessonId): InertiaResponse
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

        $script = QuestionScript::active();
        $totalQuestions = 0;
        if ($script) {
            $totalQuestions = collect($script->nodes ?? [])
                ->where('type', 'question')
                ->count();
        }

        $currentNodeDescriptor = null;
        if ($script && $response && ! $response->submitted_at) {
            $currentNodeDescriptor = $this->buildCurrentNodeDescriptor($script, $response);
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
            'currentNode' => $currentNodeDescriptor,
            'totalQuestions' => $totalQuestions,
            'turnsRemaining' => $response
                ? max(0, ChatTurnProcessor::GLOBAL_MESSAGE_CAP - $response->student_message_count)
                : ChatTurnProcessor::GLOBAL_MESSAGE_CAP,
            'awaitingFinalCheck' => (bool) ($response?->awaiting_final_check ?? false),
            // Estado de processamento assíncrono — frontend faz polling enquanto "processing".
            'chatState' => $response?->chat_state ?? LessonResponse::CHAT_STATE_IDLE,
            'draft' => Cache::get($this->draftCacheKey($lesson->id, $student->id), ''),
        ]);
    }

    /**
     * Start a new chat session for a lesson. Este turno é síncrono (não envolve IA).
     */
    public function startChat(int $lessonId): RedirectResponse
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

            $this->processor->openingTurn($script, $response);
        } finally {
            $lock->release();
        }

        return redirect()->back();
    }

    /**
     * Recebe a mensagem do aluno e enfileira o job que processa o turno (IA + transição).
     * A resposta HTTP retorna imediatamente; frontend faz polling no chat_state.
     */
    public function sendMessage(Request $request, int $lessonId): RedirectResponse
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

        $response = LessonResponse::where('lesson_id', $lesson->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        if ($response->submitted_at) {
            abort(403, 'Já finalizado.');
        }

        if ($response->isChatProcessing()) {
            return redirect()->back()->with('error', 'Aguarde — ainda estamos processando seu turno anterior.');
        }

        if (! QuestionScript::active()) {
            abort(422, 'Roteiro não configurado.');
        }

        $studentMessage = DB::transaction(function () use ($response, $validated, $lesson, $student) {
            $msg = ChatMessage::create([
                'lesson_response_id' => $response->id,
                'node_id' => $validated['node_id'],
                'role' => 'student',
                'content' => $validated['content'],
            ]);

            $response->increment('student_message_count');
            $response->update([
                'chat_state' => LessonResponse::CHAT_STATE_PROCESSING,
                'chat_state_since' => now(),
            ]);

            Cache::forget($this->draftCacheKey($lesson->id, $student->id));

            return $msg;
        });

        Log::info('Chat turn enqueued', LogContext::chat($response) + [
            'student_message_id' => $studentMessage->id,
            'node_id' => $validated['node_id'],
        ]);

        ProcessChatTurn::dispatch($response->id, $studentMessage->id);

        return redirect()->back();
    }

    public function saveDraft(Request $request, int $lessonId): RedirectResponse
    {
        $student = Auth::user();
        // Proteção IDOR: só aceita draft se o aluno tem acesso à aula.
        $subjectIds = $student->subjectsAsStudent()->pluck('subjects.id');
        $lesson = Lesson::whereIn('subject_id', $subjectIds)
            ->where('is_active', true)
            ->findOrFail($lessonId);

        $validated = $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        Cache::put(
            $this->draftCacheKey($lesson->id, $student->id),
            $validated['content'],
            now()->addHours(24),
        );

        return redirect()->back();
    }

    /**
     * Build a descriptor of the node the student is currently expected to
     * answer, based on the last bot message.
     */
    private function buildCurrentNodeDescriptor(QuestionScript $script, LessonResponse $response): ?array
    {
        $lastBot = $response->chatMessages
            ->where('role', 'bot')
            ->last();

        if (! $lastBot) {
            return null;
        }

        $nodeId = (string) $lastBot->node_id;

        if ($nodeId === ChatTurnProcessor::SENTINEL_FINAL_CHECK) {
            return [
                'id' => $nodeId,
                'type' => 'final_check',
                'collection_type' => 'free_text',
                'options' => null,
            ];
        }

        if ($nodeId === ChatTurnProcessor::SENTINEL_FINAL_TALK) {
            return [
                'id' => $nodeId,
                'type' => 'final_talk',
                'collection_type' => 'free_text',
                'options' => null,
            ];
        }

        $node = $script->getNode($nodeId);
        if (! $node) {
            return null;
        }

        if (($node['type'] ?? null) === 'end') {
            return null;
        }

        return [
            'id' => $nodeId,
            'type' => $node['type'] ?? 'question',
            'collection_type' => $node['data']['collection_type'] ?? 'free_text',
            'options' => $node['data']['options'] ?? null,
        ];
    }

    private function draftCacheKey(int $lessonId, int $studentId): string
    {
        return "chat_draft:{$studentId}:{$lessonId}";
    }
}
