<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\LessonResponse;
use App\Models\QuestionScript;
use App\Models\ResponseAlert;
use App\Services\Chat\ChatTurnProcessor;
use App\Services\ResponseAlertService;
use App\Support\LogContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job assíncrono que processa um turno de chat do aluno via IA.
 */
class ProcessChatTurn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Número máximo de tentativas. */
    public int $tries = 3;

    /** Intervalos progressivos entre tentativas (em segundos). */
    public array $backoff = [5, 20];

    /** Tempo limite de execução em segundos. */
    public int $timeout = 60;

    /**
     * Cria uma nova instância do job.
     *
     * @param  int  $lessonResponseId  ID da resposta de aula.
     * @param  int  $studentMessageId  ID da mensagem do aluno.
     */
    public function __construct(
        public readonly int $lessonResponseId,
        public readonly int $studentMessageId,
    ) {}

    /**
     * Processa o turno do aluno utilizando o processador de chat.
     *
     * @param  \App\Services\Chat\ChatTurnProcessor  $processor  Serviço de processamento de turno.
     * @return void
     */
    public function handle(ChatTurnProcessor $processor): void
    {
        $response = LessonResponse::with('chatMessages')->find($this->lessonResponseId);
        if (! $response) {
            Log::warning('ProcessChatTurn: LessonResponse not found', ['response_id' => $this->lessonResponseId]);
            return;
        }

        $studentMessage = ChatMessage::find($this->studentMessageId);
        if (! $studentMessage) {
            Log::warning('ProcessChatTurn: ChatMessage not found', LogContext::chat($response));
            $this->resetState($response);
            return;
        }

        $script = QuestionScript::active();
        if (! $script) {
            Log::error('ProcessChatTurn: no active QuestionScript', LogContext::chat($response));
            $this->resetState($response);
            return;
        }

        Log::info('ProcessChatTurn: starting', LogContext::chat($response) + [
            'student_message_id' => $studentMessage->id,
            'node_id' => $studentMessage->node_id,
        ]);

        try {
            $processor->processStudentTurn($script, $response, $studentMessage);
        } finally {
            $this->resetState($response->fresh() ?? $response);
        }

        Log::info('ProcessChatTurn: completed', LogContext::chat($response));
    }

    /**
     * Trata a falha definitiva do job e cria um alerta de resposta.
     *
     * @param  \Throwable  $e  Exceção que causou a falha.
     * @return void
     */
    public function failed(Throwable $e): void
    {
        $response = LessonResponse::find($this->lessonResponseId);
        if (! $response) {
            return;
        }

        Log::error('ProcessChatTurn: job failed after retries', LogContext::chat($response) + [
            'error' => $e->getMessage(),
        ]);

        app(ResponseAlertService::class)->raise(
            $response,
            ResponseAlert::TYPE_CLASSIFIER_FAILURE,
            ResponseAlert::SEVERITY_MEDIUM,
            mb_substr('Falha no processamento assíncrono do turno: '.$e->getMessage(), 0, 480),
        );

        $this->resetState($response);
    }

    /**
     * Repõe o estado do chat para idle caso esteja em processamento.
     *
     * @param  \App\Models\LessonResponse  $response  Resposta de aula a repor.
     * @return void
     */
    private function resetState(LessonResponse $response): void
    {
        if ($response->chat_state !== LessonResponse::CHAT_STATE_IDLE) {
            $response->update([
                'chat_state' => LessonResponse::CHAT_STATE_IDLE,
                'chat_state_since' => null,
            ]);
        }
    }
}
