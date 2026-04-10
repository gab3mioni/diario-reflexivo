<?php

namespace App\Services;

use App\Exceptions\AiProviderException;
use App\Jobs\AnalyzeDiaryResponse;
use App\Models\AiProviderConfig;
use App\Models\AnalysisPrompt;
use App\Models\DiaryAnalysis;
use App\Models\LessonResponse;
use App\Models\User;

/**
 * Serviço responsável por solicitar, aprovar e rejeitar análises de diário reflexivo.
 */
class DiaryAnalysisService
{
    /** Número máximo de análises permitidas por resposta dentro da janela de tempo. */
    public const MAX_ANALYSES_PER_RESPONSE = 3;

    /** Janela de tempo (em horas) para contagem do limite de análises. */
    public const ANALYSES_WINDOW_HOURS = 24;

    /**
     * Solicita uma nova análise de diário para a resposta, despachando o job assíncrono.
     *
     * @param  LessonResponse  $response  Resposta de aula a ser analisada.
     * @return DiaryAnalysis  Análise criada com status pendente.
     *
     * @throws AiProviderException  Se o limite for excedido, provedor/prompt não estiver ativo.
     */
    public function requestAnalysis(LessonResponse $response): DiaryAnalysis
    {
        if (! $this->canRequestAnalysis($response->id)) {
            throw AiProviderException::rateLimitExceeded(self::MAX_ANALYSES_PER_RESPONSE);
        }

        $providerConfig = AiProviderConfig::active();
        if (! $providerConfig) {
            throw AiProviderException::noActiveProvider();
        }

        $prompt = AnalysisPrompt::where('slug', 'diary-analysis')->first();
        if (! $prompt) {
            throw AiProviderException::noActivePrompt();
        }

        $latestVersion = $prompt->latestVersion;
        if (! $latestVersion) {
            throw AiProviderException::noActivePrompt();
        }

        $analysis = DiaryAnalysis::create([
            'lesson_response_id' => $response->id,
            'prompt_version_id' => $latestVersion->id,
            'ai_provider_config_id' => $providerConfig->id,
            'status' => DiaryAnalysis::STATUS_PENDING,
        ]);

        AnalyzeDiaryResponse::dispatch($analysis);

        return $analysis;
    }

    /**
     * Verifica se é possível solicitar uma nova análise para a resposta.
     *
     * @param  int  $lessonResponseId  ID da resposta de aula.
     * @return bool
     */
    public function canRequestAnalysis(int $lessonResponseId): bool
    {
        $windowStart = now()->subHours(self::ANALYSES_WINDOW_HOURS);

        $recentCount = DiaryAnalysis::where('lesson_response_id', $lessonResponseId)
            ->where('created_at', '>=', $windowStart)
            ->count();

        return $recentCount < self::MAX_ANALYSES_PER_RESPONSE;
    }

    /**
     * Aprova uma análise de diário com notas opcionais do professor.
     *
     * @param  DiaryAnalysis  $analysis  Análise a ser aprovada.
     * @param  User           $teacher   Professor que está aprovando.
     * @param  ?string        $notes     Observações do professor.
     * @return DiaryAnalysis
     */
    public function approveAnalysis(DiaryAnalysis $analysis, User $teacher, ?string $notes = null): DiaryAnalysis
    {
        $analysis->update([
            'status' => DiaryAnalysis::STATUS_APPROVED,
            'teacher_notes' => $notes,
            'reviewed_by' => $teacher->id,
            'reviewed_at' => now(),
        ]);

        return $analysis;
    }

    /**
     * Rejeita uma análise de diário com notas opcionais do professor.
     *
     * @param  DiaryAnalysis  $analysis  Análise a ser rejeitada.
     * @param  User           $teacher   Professor que está rejeitando.
     * @param  ?string        $notes     Observações do professor.
     * @return DiaryAnalysis
     */
    public function rejectAnalysis(DiaryAnalysis $analysis, User $teacher, ?string $notes = null): DiaryAnalysis
    {
        $analysis->update([
            'status' => DiaryAnalysis::STATUS_REJECTED,
            'teacher_notes' => $notes,
            'reviewed_by' => $teacher->id,
            'reviewed_at' => now(),
        ]);

        return $analysis;
    }
}
