<?php

namespace App\Jobs;

use App\Events\DiaryAnalysisUpdated;
use App\Exceptions\AiProviderException;
use App\Models\DiaryAnalysis;
use App\Models\DiaryAnalysisAlert;
use App\Services\AiProviders\AiProvider;
use App\Services\Analysis\AnalysisResult;
use App\Services\Analysis\AnalysisResultValidator;
use App\Services\Analysis\AnalysisValidationException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job assíncrono que executa a análise de IA sobre uma resposta de diário.
 *
 * A saída do provedor é validada por {@see AnalysisResultValidator} antes da
 * persistência. Falhas de validação são definitivas (não re-tentadas); erros do
 * provedor são re-lançados para acionar o backoff. A execução é idempotente: um
 * reprocessamento substitui o resultado e os alertas anteriores.
 */
class AnalyzeDiaryResponse implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Número máximo de tentativas. */
    public int $tries = 2;

    /** Intervalo em segundos entre tentativas. */
    public int $backoff = 30;

    /** Janela (segundos) do lock de unicidade, evitando bloqueio perpétuo. */
    public int $uniqueFor = 300;

    /**
     * @param  \App\Models\DiaryAnalysis  $diaryAnalysis  Registo de análise a processar.
     */
    public function __construct(
        public DiaryAnalysis $diaryAnalysis,
    ) {}

    /**
     * Chave de unicidade: impede análises concorrentes do mesmo registo.
     */
    public function uniqueId(): string
    {
        return 'analyze-diary-'.$this->diaryAnalysis->id;
    }

    /**
     * Executa a análise de IA, valida a saída e persiste resultado e alertas.
     */
    public function handle(AnalysisResultValidator $validator): void
    {
        $analysis = $this->diaryAnalysis;
        $analysis->load(['promptVersion', 'providerConfig', 'lessonResponse']);

        $provider = AiProvider::fromConfig($analysis->providerConfig);
        $systemPrompt = $analysis->promptVersion->content;
        $userContent = $analysis->lessonResponse->content;

        try {
            $raw = $provider->analyze($systemPrompt, $userContent);
        } catch (AiProviderException $e) {
            $this->markFailed(DiaryAnalysis::FAILURE_PROVIDER_ERROR, $e->getMessage());

            throw $e;
        }

        if ($raw === []) {
            $this->markFailed(DiaryAnalysis::FAILURE_PROVIDER_EMPTY, 'Provedor retornou conteúdo vazio.');

            return;
        }

        try {
            $validated = $validator->validate($raw, $userContent);
        } catch (AnalysisValidationException $e) {
            $this->markFailed($e->failureReason, $e->getMessage(), $raw);

            return;
        }

        $this->persist($validated, $raw);
    }

    /**
     * Persiste o resultado validado e substitui os alertas anteriores, de forma atômica.
     *
     * @param  array<string, mixed>  $raw  Saída bruta do provedor (armazenada cifrada).
     */
    private function persist(AnalysisResult $validated, array $raw): void
    {
        $analysis = $this->diaryAnalysis;

        DB::transaction(function () use ($analysis, $validated, $raw) {
            $analysis->update([
                'status' => DiaryAnalysis::STATUS_COMPLETED,
                'result' => $validated->toStorageArray(),
                'raw_response' => json_encode($raw),
                'error_message' => null,
                'failure_reason' => null,
            ]);

            $analysis->alerts()->delete();

            foreach ($validated->alertas as $alert) {
                $analysis->alerts()->create([
                    'lesson_response_id' => $analysis->lesson_response_id,
                    'type' => $alert['type'],
                    'severity' => $alert['severity'],
                    'title' => $alert['title'],
                    'detail' => $alert['detail'],
                    'evidence' => $alert['evidence'],
                    'confidence' => $alert['confidence'],
                    'status' => DiaryAnalysisAlert::STATUS_PENDING,
                ]);
            }
        });

        Log::info('diary_analysis.completed', [
            'analysis_id' => $analysis->id,
            'alerts' => count($validated->alertas),
            'schema_version' => $validated->schemaVersion,
        ]);

        DiaryAnalysisUpdated::dispatch($analysis->fresh());
    }

    /**
     * Marca a análise como falha, com razão estruturada e saída bruta opcional.
     *
     * @param  ?array<string, mixed>  $raw
     */
    private function markFailed(string $failureReason, string $message, ?array $raw = null): void
    {
        $this->diaryAnalysis->update([
            'status' => DiaryAnalysis::STATUS_FAILED,
            'failure_reason' => $failureReason,
            'error_message' => $message,
            'raw_response' => $raw !== null ? json_encode($raw) : $this->diaryAnalysis->raw_response,
        ]);

        Log::warning('diary_analysis.failed', [
            'analysis_id' => $this->diaryAnalysis->id,
            'failure_reason' => $failureReason,
        ]);

        DiaryAnalysisUpdated::dispatch($this->diaryAnalysis->fresh());
    }

    /**
     * Trata a falha definitiva do job após esgotamento das tentativas.
     */
    public function failed(\Throwable $exception): void
    {
        $this->diaryAnalysis->update([
            'status' => DiaryAnalysis::STATUS_FAILED,
            'failure_reason' => $this->diaryAnalysis->failure_reason ?? DiaryAnalysis::FAILURE_PROVIDER_ERROR,
            'error_message' => $exception->getMessage(),
        ]);

        DiaryAnalysisUpdated::dispatch($this->diaryAnalysis->fresh());
    }
}
