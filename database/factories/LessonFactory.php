<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'scheduled_at' => now()->subDay(),
            'is_active' => true,
        ];
    }
}
