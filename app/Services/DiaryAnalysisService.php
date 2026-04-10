<?php

namespace App\Services;

use App\Exceptions\AiProviderException;
use App\Jobs\AnalyzeDiaryResponse;
use App\Models\AiProviderConfig;
use App\Models\AnalysisPrompt;
use App\Models\DiaryAnalysis;
use App\Models\LessonResponse;
use App\Models\User;

class DiaryAnalysisService
{
    /**
     * Máximo de análises por resposta dentro da janela deslizante abaixo.
     * (Decisão do produto: janela de 24h, não lifetime.)
     */
    public const MAX_ANALYSES_PER_RESPONSE = 3;
    public const ANALYSES_WINDOW_HOURS = 24;

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
            'status' => 'pending',
        ]);

        AnalyzeDiaryResponse::dispatch($analysis);

        return $analysis;
    }

    public function canRequestAnalysis(int $lessonResponseId): bool
    {
        $windowStart = now()->subHours(self::ANALYSES_WINDOW_HOURS);

        $recentCount = DiaryAnalysis::where('lesson_response_id', $lessonResponseId)
            ->where('created_at', '>=', $windowStart)
            ->count();

        return $recentCount < self::MAX_ANALYSES_PER_RESPONSE;
    }

    public function approveAnalysis(DiaryAnalysis $analysis, User $teacher, ?string $notes = null): DiaryAnalysis
    {
        $analysis->update([
            'status' => 'approved',
            'teacher_notes' => $notes,
            'reviewed_by' => $teacher->id,
            'reviewed_at' => now(),
        ]);

        return $analysis;
    }

    public function rejectAnalysis(DiaryAnalysis $analysis, User $teacher, ?string $notes = null): DiaryAnalysis
    {
        $analysis->update([
            'status' => 'rejected',
            'teacher_notes' => $notes,
            'reviewed_by' => $teacher->id,
            'reviewed_at' => now(),
        ]);

        return $analysis;
    }
}
