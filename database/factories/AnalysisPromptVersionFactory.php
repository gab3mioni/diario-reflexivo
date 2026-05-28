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
            'version' => fn (array $attributes) => $this->nextVersionFor($attributes['analysis_prompt_id'] ?? null),
            'content' => 'You are a test classifier. Analyze the input.',
            'created_by' => null,
        ];
    }

    /**
     * Próximo número de versão para o prompt, evitando colisão com versões já
     * semeadas. Para um prompt ainda não persistido (factory aninhada), começa em 1.
     */
    private function nextVersionFor(mixed $promptId): int
    {
        if (! is_int($promptId) && ! is_string($promptId)) {
            return 1;
        }

        return (int) AnalysisPromptVersion::where('analysis_prompt_id', $promptId)->max('version') + 1;
    }
}
