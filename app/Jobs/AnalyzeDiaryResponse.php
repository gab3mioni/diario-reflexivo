<?php

namespace App\Jobs;

use App\Events\DiaryAnalysisUpdated;
use App\Exceptions\AiProviderException;
use App\Models\DiaryAnalysis;
use App\Services\AiProviders\AiProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job assíncrono que executa a análise de IA sobre uma resposta de diário.
 */
class AnalyzeDiaryResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Número máximo de tentativas. */
    public int $tries = 2;

    /** Intervalo em segundos entre tentativas. */
    public int $backoff = 30;

    /**
     * Cria uma nova instância do job.
     *
     * @param  \App\Models\DiaryAnalysis  $diaryAnalysis  Registo de análise a processar.
     */
    public function __construct(
        public DiaryAnalysis $diaryAnalysis,
    ) {}

    /**
     * Executa a análise de IA e atualiza o estado do registo.
     *
     * @return void
     */
    public function handle(): void
    {
        $analysis = $this->diaryAnalysis;

        $analysis->load(['promptVersion', 'providerConfig', 'lessonResponse']);

        $provider = AiProvider::fromConfig($analysis->providerConfig);

        $systemPrompt = $analysis->promptVersion->content;
        $userContent = $analysis->lessonResponse->content;

        try {
            $result = $provider->analyze($systemPrompt, $userContent);

            $analysis->update([
                'status' => DiaryAnalysis::STATUS_COMPLETED,
                'result' => $result,
                'raw_response' => json_encode($result),
            ]);

            DiaryAnalysisUpdated::dispatch($analysis->fresh());
        } catch (AiProviderException $e) {
            $analysis->update([
                'status' => DiaryAnalysis::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Trata a falha definitiva do job após esgotamento das tentativas.
     *
     * @param  \Throwable  $exception  Exceção que causou a falha.
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        $this->diaryAnalysis->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        DiaryAnalysisUpdated::dispatch($this->diaryAnalysis->fresh());
    }
}
