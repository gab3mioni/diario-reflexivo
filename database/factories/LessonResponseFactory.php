<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonResponseFactory extends Factory
{
    protected $model = LessonResponse::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'student_id' => User::factory(),
            'content' => '',
            'submitted_at' => null,
            'student_message_count' => 0,
            'free_talk_turn_count' => 0,
            'low_engagement_streak' => 0,
            'pending_confirm_exit_node' => null,
            'awaiting_final_check' => false,
        ];
    }
}
