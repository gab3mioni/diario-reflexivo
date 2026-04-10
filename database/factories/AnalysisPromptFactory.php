<?php

namespace Database\Factories;

use App\Models\AnalysisPrompt;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalysisPromptFactory extends Factory
{
    protected $model = AnalysisPrompt::class;

    public function definition(): array
    {
        return [
            'slug' => 'prompt-'.$this->faker->unique()->slug(2),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
        ];
    }
}
