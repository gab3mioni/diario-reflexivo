<?php

namespace App\Services\Chat;

use App\Contracts\Chat\BranchClassifierContract;
use App\Models\ChatMessage;
use App\Models\LessonResponse;
use App\Models\QuestionScript;
use App\Models\ResponseAlert;
use App\Events\LessonResponseSubmitted;
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

    /** Node ID sentinela para a verificação final ("há algo mais a compartilhar?"). */
    public const SENTINEL_FINAL_CHECK = '__final_check__';

    /** Node ID sentinela para a conversa livre final. */
    public const SENTINEL_FINAL_TALK = '__final_talk__';

    /**
     * @param  NextNodeResolver          $resolver      Resolver de próximo nó no grafo.
     * @param  BranchClassifierContract  $classifier    Classificador de ramificação por IA.
     * @param  ResponseAlertService      $alertService  Serviço de alertas.
     */
    public function __construct(
        private readonly NextNodeResolver $resolver,
        private readonly BranchClassifierContract $classifier,
        private readonly ResponseAlertService $alertService,
    ) {}

    /**
     * Processa o turno de abertura: envia mensagem de boas-vindas e entra no primeiro nó.
     *
     * @param  QuestionScript  $script    Roteiro de perguntas ativo.
     * @param  LessonResponse  $response  Resposta de aula sendo construída.
     * @return void
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
     * @param  QuestionScript  $script          Roteiro de perguntas ativo.
     * @param  LessonResponse  $response        Resposta de aula sendo construída.
     * @param  ChatMessage     $studentMessage  Mensagem enviada pelo aluno.
     * @return void
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

        $this->enterNode($script, $response, $result->nextNodeId, $result->classifierStatus, $result->classifierReason);
    }

    /**
     * Entra em um nó do grafo: dispara alertas configurados e envia a mensagem do bot.
     *
     * @param  QuestionScript  $script            Roteiro de perguntas.
     * @param  LessonResponse  $response          Resposta de aula.
     * @param  string          $nodeId            ID do nó a entrar.
     * @param  ?string         $classifierStatus  Status da classificação que levou a este nó.
     * @param  ?string         $classifierReason  Motivo da classificação.
     * @return void
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

    /**
     * Processa a resposta do aluno em um nó de conversa livre.
     *
     * @param  QuestionScript         $script    Roteiro de perguntas.
     * @param  LessonResponse         $response  Resposta de aula.
     * @param  array<string, mixed>   $node      Dados do nó de conversa livre.
     * @param  string                 $answer    Texto da resposta do aluno.
     * @return void
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

        $shouldExit = $response->free_talk_turn_count >= $maxTurns;
        $classifierStatus = null;
        $classifierReason = null;

        if (! $shouldExit) {
            try {
                $decision = $this->classifier->classifyContinuation($question, $answer);
                $shouldExit = $decision === 'exit';
                $classifierStatus = 'ok';
            } catch (BranchClassifierException $e) {
                $classifierStatus = 'failed';
                $classifierReason = mb_substr($e->getMessage(), 0, 480);
                Log::warning('Branch continuation classifier failed', LogContext::chat($response) + ['error' => $e->getMessage()]);
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

        $this->createBotMessage(
            $response,
            $node['id'],
            'Estou ouvindo. Pode continuar contando.',
            $classifierStatus,
            $classifierReason,
        );
    }

    /**
     * Dispara a verificação final perguntando se o aluno deseja compartilhar algo mais.
     *
     * @param  LessonResponse  $response  Resposta de aula.
     * @return void
     */
    private function triggerFinalCheck(LessonResponse $response): void
    {
        $response->update(['awaiting_final_check' => true]);
        $this->createBotMessage(
            $response,
            self::SENTINEL_FINAL_CHECK,
            'Antes de finalizarmos, há algo mais que você gostaria de compartilhar?',
        );
    }

    /**
     * Processa a resposta do aluno à verificação final.
     *
     * @param  QuestionScript  $script    Roteiro de perguntas.
     * @param  LessonResponse  $response  Resposta de aula.
     * @param  string          $answer    Texto da resposta do aluno.
     * @return void
     */
    private function handleFinalCheckAnswer(
        QuestionScript $script,
        LessonResponse $response,
        string $answer,
    ): void {
        $classifierStatus = 'ok';
        $classifierReason = null;
        $decision = 'exit';

        try {
            $decision = $this->classifier->classifyContinuation(
                'O aluno quer compartilhar algo mais antes de encerrar?',
                $answer,
            );
        } catch (BranchClassifierException $e) {
            $classifierStatus = 'failed';
            $classifierReason = mb_substr($e->getMessage(), 0, 480);
            Log::warning('Final-check classifier failed', LogContext::chat($response) + ['error' => $e->getMessage()]);
        }

        $response->update(['awaiting_final_check' => false]);

        if ($decision === 'exit') {
            $this->finalizeAtEndNode($script, $response, $classifierStatus, $classifierReason);

            return;
        }

        $response->update(['free_talk_turn_count' => 0]);
        $this->createBotMessage(
            $response,
            self::SENTINEL_FINAL_TALK,
            'Claro, estou aqui. Pode compartilhar o que quiser.',
            $classifierStatus,
            $classifierReason,
        );
    }

    /**
     * Processa a resposta do aluno na conversa livre final.
     *
     * @param  QuestionScript  $script    Roteiro de perguntas.
     * @param  LessonResponse  $response  Resposta de aula.
     * @param  string          $answer    Texto da resposta do aluno.
     * @return void
     */
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
                Log::warning('Final-talk classifier failed', LogContext::chat($response) + ['error' => $e->getMessage()]);
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

    /**
     * Finaliza a sessão de chat no nó final, consolidando a resposta.
     *
     * @param  QuestionScript  $script            Roteiro de perguntas.
     * @param  LessonResponse  $response          Resposta de aula.
     * @param  ?string         $classifierStatus  Status da última classificação.
     * @param  ?string         $classifierReason  Motivo da última classificação.
     * @return void
     */
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

    /**
     * Cria uma mensagem do bot no chat.
     *
     * @param  LessonResponse  $response          Resposta de aula.
     * @param  string          $nodeId            ID do nó associado à mensagem.
     * @param  string          $content           Conteúdo da mensagem.
     * @param  ?string         $classifierStatus  Status da classificação.
     * @param  ?string         $classifierReason  Motivo da classificação.
     * @return ChatMessage
     */
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
     * Consolida as mensagens do chat em conteúdo textual e finaliza a resposta.
     *
     * Despacha o evento LessonResponseSubmitted e solicita a análise de diário.
     *
     * @param  LessonResponse  $response  Resposta de aula a ser consolidada.
     * @return void
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
