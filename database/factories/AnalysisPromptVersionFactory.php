<?php

namespace Database\Factories;

use App\Models\AnalysisPrompt;
use App\Models\AnalysisPromptVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalysisPromptVersionFactory extends Factory
{
    protected $model = AnalysisPromptVersion::class;

    public function definition(): array
    {
        return [
            'analysis_prompt_id' => AnalysisPrompt::factory(),
            'version' => 1,
            'content' => 'You are a test classifier. Analyze the input.',
            'created_by' => null,
        ];
    }
}
