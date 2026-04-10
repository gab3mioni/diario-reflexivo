<?php

namespace Database\Factories;

use App\Models\AiProviderConfig;
use App\Models\AnalysisPromptVersion;
use App\Models\DiaryAnalysis;
use App\Models\LessonResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiaryAnalysisFactory extends Factory
{
    protected $model = DiaryAnalysis::class;

    public function definition(): array
    {
        return [
            'lesson_response_id' => LessonResponse::factory(),
            'prompt_version_id' => AnalysisPromptVersion::factory(),
            'ai_provider_config_id' => AiProviderConfig::factory(),
            'status' => 'pending',
            'result' => null,
            'raw_response' => null,
            'error_message' => null,
            'teacher_notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
