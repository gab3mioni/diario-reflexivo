<?php

namespace App\Services\Chat;

use App\Contracts\Chat\BranchClassifierContract;
use App\Events\LessonResponseSubmitted;
use App\Models\ChatMessage;
use App\Models\LessonResponse;
use App\Models\QuestionScript;
use App\Models\ResponseAlert;
use App\Services\DiaryAnalysisService;
use App\Services\ResponseAlertService;
use App\Support\LogContext;
use Illuminate\Support\Facades\Log;

/**
 * State-machine do chat reflexivo.
 *
 * Nenhuma chamada de IA roda dentro de DB::transaction — a transação fica
 * no controller e toda deliberação IA é feita aqui, fora de transação.
 */
class ChatTurnProcessor
{
    /** Limite global de mensagens do aluno por sessão de chat. */
    public const GLOBAL_MESSAGE_CAP = 8;

    /** Máximo de turnos na conversa livre final. */
    public const FINAL_TALK_MAX_TURNS = 3;

    /** Quantos sinais consecutivos de baixo engajamento abrem a confirmação de saída. */
    public const LOW_ENGAGEMENT_STREAK_THRESHOLD = 2;

    /** Quantas respostas anteriores do aluno são passadas ao classifier de engajamento. */
    public const RECENT_TURNS_LIMIT = 3;

    /** Node ID sentinela para a verificação final ("há algo mais a compartilhar?"). */
    public const SENTINEL_FINAL_CHECK = '__final_check__';

    /** Node ID sentinela para a conversa livre final. */
    public const SENTINEL_FINAL_TALK = '__final_talk__';

    /** Node ID sentinela para a confirmação de encerramento durante baixo engajamento persistente. */
    public const SENTINEL_CONFIRM_EXIT = '__confirm_exit__';

    /** Frase padrão de re-engajamento quando o nó não define a sua. */
    private const DEFAULT_REENGAGEMENT_MESSAGE = 'Estou aqui te ouvindo. Quer me contar um pouco mais sobre o que está pensando?';

    /** Frase que o bot envia quando entra na sentinela de confirmação de saída. */
    private const CONFIRM_EXIT_QUESTION = 'Notei que a conversa está difícil para você. Quer que a gente encerre por aqui?';

    /** Frase que o bot envia quando o aluno recusa encerrar e volta ao nó pai. */
    private const CONFIRM_EXIT_RESUME_MESSAGE = 'Beleza, vamos seguir então. Pode continuar quando quiser.';

    /**
     * @param  NextNodeResolver  $resolver  Resolver de próximo nó no grafo.
     * @param  BranchClassifierContract  $classifier  Classificador de ramificação por IA.
     * @param  ResponseAlertService  $alertService  Serviço de alertas.
     */
    public function __construct(
        private readonly NextNodeResolver $resolver,
        private readonly BranchClassifierContract $classifier,
        private readonly ResponseAlertService $alertService,
    ) {}

    /**
     * Processa o turno de abertura: envia mensagem de boas-vindas e entra no primeiro nó.
     *
     * @param  QuestionScript  $script  Roteiro de perguntas ativo.
     * @param  LessonResponse  $response  Resposta de aula sendo construída.
     */
    public function openingTurn(QuestionScript $script, LessonResponse $response): void
    {
        $startNode = $script->getStartNode();
        if (! $startNode) {
            return;
        }

        $this->createBotMessage($response, $startNode['id'], (string) ($startNode['data']['message'] ?? ''));

        $firstEdge = $script->getDefaultOutgoingEdge($startNode['id']);
        $firstNodeId = $firstEdge['target'] ?? null;
        if ($firstNodeId) {
            $this->enterNode($script, $response, (string) $firstNodeId);
        }
    }

    /**
     * Processa um turno do aluno: avalia a resposta e avança no grafo.
     *
     * @param  QuestionScript  $script  Roteiro de perguntas ativo.
     * @param  LessonResponse  $response  Resposta de aula sendo construída.
     * @param  ChatMessage  $studentMessage  Mensagem enviada pelo aluno.
     */
    public function processStudentTurn(
        QuestionScript $script,
        LessonResponse $response,
        ChatMessage $studentMessage,
    ): void {
        $nodeId = (string) $studentMessage->node_id;
        $content = (string) $studentMessage->content;

        if ($response->student_message_count >= self::GLOBAL_MESSAGE_CAP) {
            $this->alertService->raise(
                $response,
                ResponseAlert::TYPE_TURN_CAP,
                ResponseAlert::SEVERITY_LOW,
                'Limite global de mensagens atingido ('.self::GLOBAL_MESSAGE_CAP.').',
            );
            $this->finalizeAtEndNode($script, $response);

            return;
        }

        if ($nodeId === self::SENTINEL_FINAL_CHECK) {
            $this->handleFinalCheckAnswer($script, $response, $content);

            return;
        }

        if ($nodeId === self::SENTINEL_FINAL_TALK) {
            $this->handleFinalTalkAnswer($script, $response, $content);

            return;
        }

        if ($nodeId === self::SENTINEL_CONFIRM_EXIT) {
            $this->handleConfirmExitAnswer($script, $response, $content);

            return;
        }

        $currentNode = $script->getNode($nodeId);
        if (! $currentNode) {
            Log::warning('Chat turn references unknown node', LogContext::chat($response) + ['node_id' => $nodeId]);
            $this->finalizeAtEndNode($script, $response);

            return;
        }

        if (($currentNode['type'] ?? null) === 'free_talk') {
            $this->handleFreeTalkAnswer($script, $response, $currentNode, $content);

            return;
        }

        $result = $this->resolver->resolve($script, $nodeId, $content);

        if ($result->nextNodeId === null) {
            $this->finalizeAtEndNode($script, $response);

            return;
        }

        $this->enterNode(
            $script,
            $response,
            $result->nextNodeId,
            $result->classifierStatus,
            $result->classifierReason,
            $result->promptVersionId,
        );
    }

    /**
     * Entra em um nó do grafo: dispara alertas configurados e envia a mensagem do bot.
     *
     * @param  QuestionScript  $script  Roteiro de perguntas.
     * @param  LessonResponse  $response  Resposta de aula.
     * @param  string  $nodeId  ID do nó a entrar.
     * @param  ?string  $classifierStatus  Status da classificação que levou a este nó.
     * @param  ?string  $classifierReason  Motivo da classificação.
     * @param  ?int  $promptVersionId  Versão do prompt usada na classificação que conduziu até aqui.
     */
    private function enterNode(
        QuestionScript $script,
        LessonResponse $response,
        string $nodeId,
        ?string $classifierStatus = null,
        ?string $classifierReason = null,
        ?int $promptVersionId = null,
    ): void {
        $node = $script->getNode($nodeId);
        if (! $node) {
            return;
        }

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
            $this->triggerFinalCheck($response, $classifierStatus, $classifierReason, $promptVersionId);

            return;
        }

        $this->createBotMessage(
            $response,
            $nodeId,
            (string) ($node['data']['message'] ?? ''),
            $classifierStatus,
            $classifierReason,
            $promptVersionId,
        );

        if (($node['type'] ?? null) === 'free_talk') {
            $response->update([
                'free_talk_turn_count' => 0,
                'low_engagement_streak' => 0,
            ]);
        }
    }

    /**
     * Processa a resposta do aluno em um nó de conversa livre.
     *
     * @param  QuestionScript  $script  Roteiro de perguntas.
     * @param  LessonResponse  $response  Resposta de aula.
     * @param  array<string, mixed>  $node  Dados do nó de conversa livre.
     * @param  string  $answer  Texto da resposta do aluno.
     */
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
        $nodeId = (string) $node['id'];

        if ($response->free_talk_turn_count >= $maxTurns) {
            $this->closeFreeTalk($script, $response, $node, null, null, null);

            return;
        }

        $recentTurns = $this->loadRecentStudentTurns($response, $nodeId, self::RECENT_TURNS_LIMIT, $answer);

        try {
            $decision = $this->classifier->classifyEngagement($question, $answer, $recentTurns);
        } catch (BranchClassifierException $e) {
            Log::warning('Engagement classifier failed', LogContext::chat($response) + ['error' => $e->getMessage()]);
            $this->createBotMessage(
                $response,
                $nodeId,
                'Estou ouvindo. Pode continuar contando.',
                'failed',
                mb_substr($e->getMessage(), 0, 480),
                null,
            );

            return;
        }

        $classifierStatus = 'ok';
        $classifierReason = $decision->rationale;
        $promptVersionId = $decision->promptVersionId;

        switch ($decision->decision) {
            case EngagementDecision::DECISION_EXIT:
                $response->update(['low_engagement_streak' => 0]);
                $this->closeFreeTalk($script, $response, $node, $classifierStatus, $classifierReason, $promptVersionId);

                return;

            case EngagementDecision::DECISION_ASK_TO_END:
                $this->triggerConfirmExit($response, $nodeId, $classifierStatus, $classifierReason, $promptVersionId);

                return;

            case EngagementDecision::DECISION_REENGAGE:
                $response->increment('low_engagement_streak');
                $response->refresh();

                if ($response->low_engagement_streak >= self::LOW_ENGAGEMENT_STREAK_THRESHOLD) {
                    $this->triggerConfirmExit($response, $nodeId, $classifierStatus, $classifierReason, $promptVersionId);

                    return;
                }

                $reengagement = (string) ($node['data']['reengagement_message'] ?? self::DEFAULT_REENGAGEMENT_MESSAGE);
                $this->createBotMessage($response, $nodeId, $reengagement, $classifierStatus, $classifierReason, $promptVersionId);

                return;

            case EngagementDecision::DECISION_CONTINUE:
            default:
                $response->update(['low_engagement_streak' => 0]);
                $this->createBotMessage(
                    $response,
                    $nodeId,
                    'Estou ouvindo. Pode continuar contando.',
                    $classifierStatus,
                    $classifierReason,
                    $promptVersionId,
                );

                return;
        }
    }

    /**
     * Encerra um nó de conversa livre (real, não sentinela), enviando o closing_message
     * e seguindo a aresta padrão. Se não houver aresta, dispara o final_check.
     *
     * @param  array<string, mixed>  $node
     */
    private function closeFreeTalk(
        QuestionScript $script,
        LessonResponse $response,
        array $node,
        ?string $classifierStatus,
        ?string $classifierReason,
        ?int $promptVersionId,
    ): void {
        $closing = (string) ($node['data']['closing_message'] ?? 'Obrigado por compartilhar.');
        $this->createBotMessage($response, (string) $node['id'], $closing, $classifierStatus, $classifierReason, $promptVersionId);

        $default = $script->getDefaultOutgoingEdge((string) $node['id']);
        if ($default && isset($default['target'])) {
            $this->enterNode($script, $response, (string) $default['target'], $classifierStatus, $classifierReason, $promptVersionId);
        } else {
            $this->triggerFinalCheck($response, $classifierStatus, $classifierReason, $promptVersionId);
        }
    }

    /**
     * Dispara a verificação final perguntando se o aluno deseja compartilhar algo mais.
     *
     * Propaga o status da classificação que levou até aqui para que a mensagem
     * final preserve a rastreabilidade (ex.: falha do classificador no confirm_exit).
     *
     * @param  LessonResponse  $response  Resposta de aula.
     * @param  ?string  $classifierStatus  Status da classificação que disparou o final_check.
     * @param  ?string  $classifierReason  Motivo da classificação.
     * @param  ?int  $promptVersionId  Versão do prompt da decisão.
     */
    private function triggerFinalCheck(
        LessonResponse $response,
        ?string $classifierStatus = null,
        ?string $classifierReason = null,
        ?int $promptVersionId = null,
    ): void {
        $response->update(['awaiting_final_check' => true]);
        $this->createBotMessage(
            $response,
            self::SENTINEL_FINAL_CHECK,
            'Antes de finalizarmos, há algo mais que você gostaria de compartilhar?',
            $classifierStatus,
            $classifierReason,
            $promptVersionId,
        );
    }

    /**
     * Processa a resposta do aluno à verificação final.
     *
     * @param  QuestionScript  $script  Roteiro de perguntas.
     * @param  LessonResponse  $response  Resposta de aula.
     * @param  string  $answer  Texto da resposta do aluno.
     */
    private function handleFinalCheckAnswer(
        QuestionScript $script,
        LessonResponse $response,
        string $answer,
    ): void {
        $classifierStatus = 'ok';
        $classifierReason = null;
        $promptVersionId = null;
        $decisionValue = 'exit';

        try {
            $decision = $this->classifier->classifyContinuation(
                'O aluno quer compartilhar algo mais antes de encerrar?',
                $answer,
            );
            $decisionValue = $decision->decision;
            $promptVersionId = $decision->promptVersionId;
        } catch (BranchClassifierException $e) {
            $classifierStatus = 'failed';
            $classifierReason = mb_substr($e->getMessage(), 0, 480);
            Log::warning('Final-check classifier failed', LogContext::chat($response) + ['error' => $e->getMessage()]);
        }

        $response->update(['awaiting_final_check' => false]);

        if ($decisionValue === 'exit') {
            $this->finalizeAtEndNode($script, $response, $classifierStatus, $classifierReason, $promptVersionId);

            return;
        }

        $response->update(['free_talk_turn_count' => 0]);
        $this->createBotMessage(
            $response,
            self::SENTINEL_FINAL_TALK,
            'Claro, estou aqui. Pode compartilhar o que quiser.',
            $classifierStatus,
            $classifierReason,
            $promptVersionId,
        );
    }

    /**
     * Processa a resposta do aluno na conversa livre final.
     *
     * @param  QuestionScript  $script  Roteiro de perguntas.
     * @param  LessonResponse  $response  Resposta de aula.
     * @param  string  $answer  Texto da resposta do aluno.
     */
    private function handleFinalTalkAnswer(
        QuestionScript $script,
        LessonResponse $response,
        string $answer,
    ): void {
        $response->increment('free_talk_turn_count');
        $response->refresh();

        if ($response->free_talk_turn_count >= self::FINAL_TALK_MAX_TURNS) {
            $this->finalizeAtEndNode($script, $response, null, null, null);

            return;
        }

        $recentTurns = $this->loadRecentStudentTurns(
            $response,
            self::SENTINEL_FINAL_TALK,
            self::RECENT_TURNS_LIMIT,
            $answer,
        );

        try {
            $decision = $this->classifier->classifyEngagement(
                'O aluno ainda quer compartilhar algo ou terminou?',
                $answer,
                $recentTurns,
            );
        } catch (BranchClassifierException $e) {
            // Falha: continua a conversa final livre, sem mexer no streak.
            Log::warning('Final-talk engagement classifier failed', LogContext::chat($response) + ['error' => $e->getMessage()]);
            $this->createBotMessage(
                $response,
                self::SENTINEL_FINAL_TALK,
                'Entendi. Pode continuar, estou te ouvindo.',
                'failed',
                mb_substr($e->getMessage(), 0, 480),
                null,
            );

            return;
        }

        $classifierStatus = 'ok';
        $classifierReason = $decision->rationale;
        $promptVersionId = $decision->promptVersionId;

        switch ($decision->decision) {
            case EngagementDecision::DECISION_EXIT:
                $response->update(['low_engagement_streak' => 0]);
                $this->finalizeAtEndNode($script, $response, $classifierStatus, $classifierReason, $promptVersionId);

                return;

            case EngagementDecision::DECISION_ASK_TO_END:
                $this->triggerConfirmExit(
                    $response,
                    self::SENTINEL_FINAL_TALK,
                    $classifierStatus,
                    $classifierReason,
                    $promptVersionId,
                );

                return;

            case EngagementDecision::DECISION_REENGAGE:
                $response->increment('low_engagement_streak');
                $response->refresh();

                if ($response->low_engagement_streak >= self::LOW_ENGAGEMENT_STREAK_THRESHOLD) {
                    $this->triggerConfirmExit(
                        $response,
                        self::SENTINEL_FINAL_TALK,
                        $classifierStatus,
                        $classifierReason,
                        $promptVersionId,
                    );

                    return;
                }

                $this->createBotMessage(
                    $response,
                    self::SENTINEL_FINAL_TALK,
                    self::DEFAULT_REENGAGEMENT_MESSAGE,
                    $classifierStatus,
                    $classifierReason,
                    $promptVersionId,
                );

                return;

            case EngagementDecision::DECISION_CONTINUE:
            default:
                $response->update(['low_engagement_streak' => 0]);
                $this->createBotMessage(
                    $response,
                    self::SENTINEL_FINAL_TALK,
                    'Entendi. Pode continuar, estou te ouvindo.',
                    $classifierStatus,
                    $classifierReason,
                    $promptVersionId,
                );

                return;
        }
    }

    /**
     * Finaliza a sessão de chat no nó final, consolidando a resposta.
     *
     * @param  QuestionScript  $script  Roteiro de perguntas.
     * @param  LessonResponse  $response  Resposta de aula.
     * @param  ?string  $classifierStatus  Status da última classificação.
     * @param  ?string  $classifierReason  Motivo da última classificação.
     * @param  ?int  $promptVersionId  Versão do prompt usada na última classificação.
     */
    private function finalizeAtEndNode(
        QuestionScript $script,
        LessonResponse $response,
        ?string $classifierStatus = null,
        ?string $classifierReason = null,
        ?int $promptVersionId = null,
    ): void {
        $endNode = collect($script->nodes ?? [])->firstWhere('type', 'end');

        if ($endNode) {
            $this->createBotMessage(
                $response,
                (string) $endNode['id'],
                (string) ($endNode['data']['message'] ?? 'Obrigado pela sua reflexão.'),
                $classifierStatus,
                $classifierReason,
                $promptVersionId,
            );
        }

        $response->update(['awaiting_final_check' => false]);
        $this->consolidateResponse($response);
    }

    /**
     * Cria uma mensagem do bot no chat.
     *
     * @param  LessonResponse  $response  Resposta de aula.
     * @param  string  $nodeId  ID do nó associado à mensagem.
     * @param  string  $content  Conteúdo da mensagem.
     * @param  ?string  $classifierStatus  Status da classificação.
     * @param  ?string  $classifierReason  Motivo da classificação.
     * @param  ?int  $promptVersionId  Versão do prompt usada pelo classifier.
     */
    private function createBotMessage(
        LessonResponse $response,
        string $nodeId,
        string $content,
        ?string $classifierStatus = null,
        ?string $classifierReason = null,
        ?int $promptVersionId = null,
    ): ChatMessage {
        return ChatMessage::create([
            'lesson_response_id' => $response->id,
            'node_id' => $nodeId,
            'role' => 'bot',
            'content' => $content,
            'classifier_status' => $classifierStatus,
            'classifier_reason' => $classifierReason,
            'prompt_version_id' => $promptVersionId,
        ]);
    }

    /**
     * Carrega as últimas N respostas do aluno no mesmo nó, ordem antiga → recente.
     *
     * O turno atual já está persistido como a mensagem mais nova, então é excluído
     * para não ficar duplicado em "answer" + "recent_turns".
     *
     * @param  LessonResponse  $response  Resposta de aula.
     * @param  string  $nodeId  ID do nó (ou sentinela) usado para filtrar.
     * @param  int  $limit  Máximo de turnos retornados.
     * @param  string  $currentAnswer  Resposta atual do aluno (excluída do retorno).
     * @return array<int, string>
     */
    private function loadRecentStudentTurns(
        LessonResponse $response,
        string $nodeId,
        int $limit,
        string $currentAnswer,
    ): array {
        $messages = $response->chatMessages()
            ->where('role', 'student')
            ->where('node_id', $nodeId)
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get(['content', 'id']);

        // Remove o turno atual (mais recente com o mesmo conteúdo) e devolve em ordem cronológica.
        $skipped = false;
        $filtered = [];
        foreach ($messages as $msg) {
            if (! $skipped && (string) $msg->content === $currentAnswer) {
                $skipped = true;

                continue;
            }
            $filtered[] = (string) $msg->content;
        }

        return array_reverse(array_slice($filtered, 0, $limit));
    }

    /**
     * Dispara a sentinela de confirmação de saída por baixo engajamento persistente.
     *
     * Persiste o nó pai para que, se o aluno recusar encerrar, o condutor saiba
     * para onde voltar. Zera o streak para dar nova chance pós-confirmação.
     *
     * @param  LessonResponse  $response  Resposta de aula.
     * @param  string  $returnNodeId  ID do nó pai (free_talk real ou SENTINEL_FINAL_TALK).
     * @param  ?string  $classifierStatus  Status da classificação que disparou o confirm_exit.
     * @param  ?string  $classifierReason  Motivo da classificação.
     * @param  ?int  $promptVersionId  Versão do prompt da decisão.
     */
    private function triggerConfirmExit(
        LessonResponse $response,
        string $returnNodeId,
        ?string $classifierStatus,
        ?string $classifierReason,
        ?int $promptVersionId,
    ): void {
        $response->update([
            'pending_confirm_exit_node' => $returnNodeId,
            'low_engagement_streak' => 0,
        ]);

        $this->createBotMessage(
            $response,
            self::SENTINEL_CONFIRM_EXIT,
            self::CONFIRM_EXIT_QUESTION,
            $classifierStatus,
            $classifierReason,
            $promptVersionId,
        );
    }

    /**
     * Processa a resposta do aluno à sentinela __confirm_exit__.
     *
     * Decisão binária via classifyContinuation (modo continuation, idêntico ao final_check):
     * - "continue" (quer continuar conversando) → volta ao nó pai sem encerrar.
     * - "exit" (quer encerrar) → encerra a conversa livre do nó pai.
     *
     * Em falha do classifier, usa "exit" como default — o aluno só chegou aqui
     * por baixo engajamento persistente e a falha não deve segurar quem quer sair.
     *
     * @param  QuestionScript  $script  Roteiro de perguntas.
     * @param  LessonResponse  $response  Resposta de aula.
     * @param  string  $answer  Texto da resposta do aluno.
     */
    private function handleConfirmExitAnswer(
        QuestionScript $script,
        LessonResponse $response,
        string $answer,
    ): void {
        $returnNodeId = (string) $response->pending_confirm_exit_node;

        $classifierStatus = 'ok';
        $classifierReason = null;
        $promptVersionId = null;
        $decisionValue = 'exit';

        try {
            $decision = $this->classifier->classifyContinuation(
                'O aluno quer continuar conversando ou prefere encerrar agora?',
                $answer,
            );
            $decisionValue = $decision->decision;
            $promptVersionId = $decision->promptVersionId;
        } catch (BranchClassifierException $e) {
            $classifierStatus = 'failed';
            $classifierReason = mb_substr($e->getMessage(), 0, 480);
            Log::warning('Confirm-exit classifier failed', LogContext::chat($response) + ['error' => $e->getMessage()]);
        }

        $response->update(['pending_confirm_exit_node' => null]);

        if ($decisionValue === 'continue') {
            // Aluno quer seguir. Volta ao nó pai com mensagem de retomada.
            $this->createBotMessage(
                $response,
                $returnNodeId,
                self::CONFIRM_EXIT_RESUME_MESSAGE,
                $classifierStatus,
                $classifierReason,
                $promptVersionId,
            );

            return;
        }

        // Aluno aceitou encerrar (ou falha → default exit).
        if ($returnNodeId === self::SENTINEL_FINAL_TALK) {
            $this->finalizeAtEndNode($script, $response, $classifierStatus, $classifierReason, $promptVersionId);

            return;
        }

        $parentNode = $script->getNode($returnNodeId);
        if (! $parentNode) {
            $this->finalizeAtEndNode($script, $response, $classifierStatus, $classifierReason, $promptVersionId);

            return;
        }

        $this->closeFreeTalk($script, $response, $parentNode, $classifierStatus, $classifierReason, $promptVersionId);
    }

    /**
     * Consolida as mensagens do chat em conteúdo textual e finaliza a resposta.
     *
     * Despacha o evento LessonResponseSubmitted e solicita a análise de diário.
     *
     * @param  LessonResponse  $response  Resposta de aula a ser consolidada.
     */
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

        Log::info('Chat session finalized', LogContext::chat($response) + [
            'message_count' => $response->student_message_count,
        ]);

        LessonResponseSubmitted::dispatch($response->fresh());

        try {
            app(DiaryAnalysisService::class)->requestAnalysis($response);
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch diary analysis', LogContext::chat($response) + ['error' => $e->getMessage()]);
            $this->alertService->raise(
                $response,
                ResponseAlert::TYPE_CLASSIFIER_FAILURE,
                ResponseAlert::SEVERITY_MEDIUM,
                mb_substr('Falha ao enfileirar análise do diário: '.$e->getMessage(), 0, 480),
            );
        }
    }
}
