<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\LessonResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'lesson_response_id' => LessonResponse::factory(),
            'node_id' => 'node-'.$this->faker->unique()->numberBetween(1, 99999),
            'role' => 'bot',
            'content' => $this->faker->sentence(),
            'classifier_status' => null,
            'classifier_reason' => null,
        ];
    }

    public function bot(): static
    {
        return $this->state(fn () => ['role' => 'bot']);
    }

    public function student(): static
    {
        return $this->state(fn () => [
            'role' => 'student',
            'classifier_status' => null,
        ]);
    }

    public function classified(string $status = 'ok', ?string $reason = null): static
    {
        return $this->state(fn () => [
            'role' => 'bot',
            'classifier_status' => $status,
            'classifier_reason' => $reason,
        ]);
    }
}
