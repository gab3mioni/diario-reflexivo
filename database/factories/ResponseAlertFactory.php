<?php

namespace Database\Factories;

use App\Models\LessonResponse;
use App\Models\ResponseAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResponseAlertFactory extends Factory
{
    protected $model = ResponseAlert::class;

    public function definition(): array
    {
        return [
            'lesson_response_id' => LessonResponse::factory(),
            'type' => ResponseAlert::TYPE_ABSENCE,
            'severity' => ResponseAlert::SEVERITY_LOW,
            'reason' => null,
            'read_at' => null,
        ];
    }
}
