<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\QuestionScript;
use App\Models\ResponseAlert;
use App\Services\Chat\BranchClassifier;
use App\Services\Chat\BranchClassifierException;
use App\Services\Chat\NextNodeResolver;
use App\Services\DiaryAnalysisService;
use App\Services\ResponseAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StudentLessonsController extends Controller
{
    /**
     * Hard cap on the number of student messages per diary session. Prevents
     * runaway free-talk loops and bounds IA cost.
     */
    private const GLOBAL_MESSAGE_CAP = 8;

    /**
     * Max turns inside the post-final-check free talk.
     */
    private const FINAL_TALK_MAX_TURNS = 3;

    private const SENTINEL_FINAL_CHECK = '__final_check__';
    private const SENTINEL_FINAL_TALK = '__final_talk__';

    public function __construct(
        private readonly NextNodeResolver $resolver,
        private readonly BranchClassifier $classifier,
        private readonly ResponseAlertService $alertService,
    ) {
    }

    /**
     * List all lessons for the student, grouped by status.
     */
    public function index()
    {
        $student = Auth::user();

        $subjectIds = $student->subjectsAsStudent()->pluck('subjects.id');

        $lessons = Lesson::whereIn('subject_id', $subjectIds)
            ->where('is_active', true)
            ->with('subject')
            ->orderBy('scheduled_at', 'desc')
            ->get();

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
                ? max(0, self::GLOBAL_MESSAGE_CAP - $response->student_message_count)
                : self::GLOBAL_MESSAGE_CAP,
            'awaitingFinalCheck' => (bool) ($response?->awaiting_final_check ?? false),
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

            $startNode = $script->getStartNode();
            if (! $startNode) {
                abort(422, 'Roteiro sem nó inicial.');
            }

            $this->createBotMessage($response, $startNode['id'], $startNode['data']['message'] ?? '');

            $firstEdge = $script->getDefaultOutgoingEdge($startNode['id']);
            $firstNodeId = $firstEdge['target'] ?? null;

            if ($firstNodeId) {
                $this->enterNode($script, $response, $firstNodeId);
            }
        } finally {
            $lock->release();
        }

        return redirect()->back();
    }

    /**
     * Send a chat message (student response).
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

        $response = LessonResponse::where('lesson_id', $lesson->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        if ($response->submitted_at) {
            abort(403, 'Já finalizado.');
        }

        $script = QuestionScript::active();
        if (! $script) {
            abort(422, 'Roteiro não configurado.');
        }

        ChatMessage::create([
            'lesson_response_id' => $response->id,
            'node_id' => $validated['node_id'],
            'role' => 'student',
            'content' => $validated['content'],
        ]);

        $response->increment('student_message_count');
        $response->refresh();

        Cache::forget($this->draftCacheKey($lesson->id, $student->id));

        // Global turn cap — force finalize regardless of current state.
        if ($response->student_message_count >= self::GLOBAL_MESSAGE_CAP) {
            $this->alertService->raise(
                $response,
                ResponseAlert::TYPE_TURN_CAP,
                ResponseAlert::SEVERITY_LOW,
                'Limite global de mensagens atingido ('.self::GLOBAL_MESSAGE_CAP.').',
            );
            $this->finalizeAtEndNode($script, $response);

            return redirect()->back();
        }

        // Dispatch by current state.
        if ($validated['node_id'] === self::SENTINEL_FINAL_CHECK) {
            $this->handleFinalCheckAnswer($script, $response, $validated['content']);

            return redirect()->back();
        }

        if ($validated['node_id'] === self::SENTINEL_FINAL_TALK) {
            $this->handleFinalTalkAnswer($script, $response, $validated['content']);

            return redirect()->back();
        }

        $currentNode = $script->getNode($validated['node_id']);
        if (! $currentNode) {
            abort(422, 'Nó não encontrado no roteiro.');
        }

        if (($currentNode['type'] ?? null) === 'free_talk') {
            $this->handleFreeTalkAnswer($script, $response, $currentNode, $validated['content']);

            return redirect()->back();
        }

        $result = $this->resolver->resolve($script, $validated['node_id'], $validated['content']);

        if ($result->nextNodeId === null) {
            // Broken graph; force end.
            $this->finalizeAtEndNode($script, $response);

            return redirect()->back();
        }

        $this->enterNode($script, $response, $result->nextNodeId, $result->classifierStatus, $result->classifierReason);

        return redirect()->back();
    }

    /**
     * Walk into a node: fire any alerts declared on it, post its bot message,
     * and transition state (or intercept with final check when entering an
     * end node).
     */
    private function enterNode(
        QuestionScript $script,
        LessonResponse $response,
        string $nodeId,
        ?string $classifierStatus = null,
        ?string $classifierReason = null,
    ): void {
        $node = $script->getNode($nodeId);
        if (! $node) {
            return;
        }

        // Raise any alert attached to the new node before anything else.
        if (! empty($node['data']['alert']) && is_array($node['data']['alert'])) {
            $alert = $node['data']['alert'];
            $this->alertService->raise(
                $response,
                (string) ($alert['type'] ?? ResponseAlert::TYPE_ABSENCE),
                (string) ($alert['severity'] ?? ResponseAlert::SEVERITY_MEDIUM),
                $alert['reason'] ?? null,
            );
        }

        if (($node['type'] ?? null) === 'end') {
            $this->triggerFinalCheck($response);

            return;
        }

        $this->createBotMessage(
            $response,
            $nodeId,
            (string) ($node['data']['message'] ?? ''),
            $classifierStatus,
            $classifierReason,
        );

        if (($node['type'] ?? null) === 'free_talk') {
            $response->update(['free_talk_turn_count' => 0]);
        }
    }

    private function handleFreeTalkAnswer(
        QuestionScript $script,
        LessonResponse $response,
        array $node,
        string $answer,
    ): void {
        $response->increment('free_talk_turn_count');
        $response->refresh();

        $maxTurns = (int) ($node['data']['max_turns'] ?? 3);
        $question = (string) ($node['data']['message'] ?? '');

        $shouldExit = $response->free_talk_turn_count >= $maxTurns;
        $classifierStatus = null;
        $classifierReason = null;

        if (! $shouldExit) {
            try {
                $decision = $this->classifier->classifyContinuation($question, $answer);
                $shouldExit = $decision === 'exit';
                $classifierStatus = 'ok';
            } catch (BranchClassifierException $e) {
                // On failure, keep the loop going (safer for the student) — they
                // can still trigger exit via the turn cap.
                $classifierStatus = 'failed';
                $classifierReason = mb_substr($e->getMessage(), 0, 480);
                Log::warning('Branch continuation classifier failed', [
                    'response_id' => $response->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($shouldExit) {
            $closing = (string) ($node['data']['closing_message'] ?? 'Obrigado por compartilhar.');
            $this->createBotMessage($response, $node['id'], $closing, $classifierStatus, $classifierReason);

            $default = $script->getDefaultOutgoingEdge($node['id']);
            if ($default && isset($default['target'])) {
                $this->enterNode($script, $response, (string) $default['target']);
            } else {
                $this->triggerFinalCheck($response);
            }

            return;
        }

        // Continue the loop — acknowledge and stay at the same node.
        $this->createBotMessage(
            $response,
            $node['id'],
            'Estou ouvindo. Pode continuar contando.',
            $classifierStatus,
            $classifierReason,
        );
    }

    private function triggerFinalCheck(LessonResponse $response): void
    {
        $response->update(['awaiting_final_check' => true]);
        $this->createBotMessage(
            $response,
            self::SENTINEL_FINAL_CHECK,
            'Antes de finalizarmos, há algo mais que você gostaria de compartilhar?',
        );
    }

    private function handleFinalCheckAnswer(
        QuestionScript $script,
        LessonResponse $response,
        string $answer,
    ): void {
        $classifierStatus = 'ok';
        $classifierReason = null;

        try {
            $decision = $this->classifier->classifyContinuation(
                'O aluno quer compartilhar algo mais antes de encerrar?',
                $answer,
            );
        } catch (BranchClassifierException $e) {
            // On failure, assume the student wants to finish — safer than
            // trapping them in an extra loop.
            $decision = 'exit';
            $classifierStatus = 'failed';
            $classifierReason = mb_substr($e->getMessage(), 0, 480);
            Log::warning('Final-check classifier failed', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
            ]);
        }

        $response->update(['awaiting_final_check' => false]);

        if ($decision === 'exit') {
            $this->finalizeAtEndNode($script, $response, $classifierStatus, $classifierReason);

            return;
        }

        // Student wants to keep talking — open a short final free-talk loop.
        $response->update(['free_talk_turn_count' => 0]);
        $this->createBotMessage(
            $response,
            self::SENTINEL_FINAL_TALK,
            'Claro, estou aqui. Pode compartilhar o que quiser.',
            $classifierStatus,
            $classifierReason,
        );
    }

    private function handleFinalTalkAnswer(
        QuestionScript $script,
        LessonResponse $response,
        string $answer,
    ): void {
        $response->increment('free_talk_turn_count');
        $response->refresh();

        $classifierStatus = null;
        $classifierReason = null;
        $shouldExit = $response->free_talk_turn_count >= self::FINAL_TALK_MAX_TURNS;

        if (! $shouldExit) {
            try {
                $shouldExit = $this->classifier->classifyContinuation(
                    'O aluno ainda quer compartilhar algo ou terminou?',
                    $answer,
                ) === 'exit';
                $classifierStatus = 'ok';
            } catch (BranchClassifierException $e) {
                $shouldExit = true;
                $classifierStatus = 'failed';
                $classifierReason = mb_substr($e->getMessage(), 0, 480);
                Log::warning('Final-talk classifier failed', [
                    'response_id' => $response->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($shouldExit) {
            $this->finalizeAtEndNode($script, $response, $classifierStatus, $classifierReason);

            return;
        }

        $this->createBotMessage(
            $response,
            self::SENTINEL_FINAL_TALK,
            'Entendi. Pode continuar, estou te ouvindo.',
            $classifierStatus,
            $classifierReason,
        );
    }

    private function finalizeAtEndNode(
        QuestionScript $script,
        LessonResponse $response,
        ?string $classifierStatus = null,
        ?string $classifierReason = null,
    ): void {
        $endNode = collect($script->nodes ?? [])->firstWhere('type', 'end');

        if ($endNode) {
            $this->createBotMessage(
                $response,
                (string) $endNode['id'],
                (string) ($endNode['data']['message'] ?? 'Obrigado pela sua reflexão.'),
                $classifierStatus,
                $classifierReason,
            );
        }

        $response->update(['awaiting_final_check' => false]);
        $this->consolidateResponse($response);
    }

    private function createBotMessage(
        LessonResponse $response,
        string $nodeId,
        string $content,
        ?string $classifierStatus = null,
        ?string $classifierReason = null,
    ): ChatMessage {
        return ChatMessage::create([
            'lesson_response_id' => $response->id,
            'node_id' => $nodeId,
            'role' => 'bot',
            'content' => $content,
            'classifier_status' => $classifierStatus,
            'classifier_reason' => $classifierReason,
        ]);
    }

    /**
     * Build a descriptor of the node the student is currently expected to
     * answer, based on the last bot message. Includes collection_type and
     * options for option-nodes, and handles the two runtime sentinels.
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

        if ($nodeId === self::SENTINEL_FINAL_CHECK) {
            return [
                'id' => $nodeId,
                'type' => 'final_check',
                'collection_type' => 'free_text',
                'options' => null,
            ];
        }

        if ($nodeId === self::SENTINEL_FINAL_TALK) {
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

    private function consolidateResponse(LessonResponse $response): void
    {
        if ($response->submitted_at) {
            return;
        }

        $studentMessages = $response->chatMessages()
            ->where('role', 'student')
            ->get();

        $botMessages = $response->chatMessages()
            ->where('role', 'bot')
            ->get()
            ->groupBy('node_id')
            ->map(fn ($group) => $group->first());

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
