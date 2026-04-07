<?php

namespace App\Jobs;

use App\Exceptions\AiProviderException;
use App\Models\DiaryAnalysis;
use App\Services\AiProviders\AiProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeDiaryResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public DiaryAnalysis $diaryAnalysis,
    ) {}

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
                'status' => 'completed',
                'result' => $result,
                'raw_response' => json_encode($result),
            ]);
        } catch (AiProviderException $e) {
            $analysis->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->diaryAnalysis->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
