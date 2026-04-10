<?php

namespace Database\Factories;

use App\Models\QuestionScript;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionScript>
 */
class QuestionScriptFactory extends Factory
{
    protected $model = QuestionScript::class;

    /**
     * Grafo mínimo válido: start → q1 (free_text) → end.
     */
    public function definition(): array
    {
        return [
            'name' => 'Script '.$this->faker->unique()->numberBetween(1, 99999),
            'description' => $this->faker->sentence(),
            'is_active' => false,
            'nodes' => [
                ['id' => 'start', 'type' => 'start', 'data' => []],
                [
                    'id' => 'q1',
                    'type' => 'question',
                    'data' => [
                        'collection_type' => 'free_text',
                        'message' => 'Como você se sente hoje?',
                    ],
                ],
                ['id' => 'end', 'type' => 'end', 'data' => []],
            ],
            'edges' => [
                ['id' => 'e-start-q1', 'source' => 'start', 'target' => 'q1', 'is_default' => true],
                ['id' => 'e-q1-end', 'source' => 'q1', 'target' => 'end', 'is_default' => true],
            ],
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    /**
     * Grafo com branching: q1 com duas saídas não-default + uma default.
     */
    public function withBranching(): static
    {
        return $this->state(fn () => [
            'nodes' => [
                ['id' => 'start', 'type' => 'start', 'data' => []],
                [
                    'id' => 'q1',
                    'type' => 'question',
                    'data' => [
                        'collection_type' => 'free_text',
                        'message' => 'Descreva seu dia.',
                    ],
                ],
                ['id' => 'positive', 'type' => 'question', 'data' => ['collection_type' => 'free_text', 'message' => 'Que bom!']],
                ['id' => 'negative', 'type' => 'question', 'data' => ['collection_type' => 'free_text', 'message' => 'Sinto muito.']],
                ['id' => 'end', 'type' => 'end', 'data' => []],
            ],
            'edges' => [
                ['id' => 'e0', 'source' => 'start', 'target' => 'q1', 'is_default' => true],
                ['id' => 'e1', 'source' => 'q1', 'target' => 'positive', 'condition' => ['description' => 'sentimento positivo']],
                ['id' => 'e2', 'source' => 'q1', 'target' => 'negative', 'condition' => ['description' => 'sentimento negativo']],
                ['id' => 'e3', 'source' => 'q1', 'target' => 'end', 'is_default' => true],
                ['id' => 'e4', 'source' => 'positive', 'target' => 'end', 'is_default' => true],
                ['id' => 'e5', 'source' => 'negative', 'target' => 'end', 'is_default' => true],
            ],
        ]);
    }
}
