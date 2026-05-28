<?php

namespace Database\Factories;

use App\Models\DiaryAnalysis;
use App\Models\DiaryAnalysisAlert;
use App\Models\LessonResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiaryAnalysisAlertFactory extends Factory
{
    protected $model = DiaryAnalysisAlert::class;

    public function definition(): array
    {
        return [
            'diary_analysis_id' => DiaryAnalysis::factory(),
            'lesson_response_id' => LessonResponse::factory(),
            'type' => 'desmotivacao',
            'severity' => DiaryAnalysisAlert::SEVERITY_WARNING,
            'title' => $this->faker->sentence(4),
            'detail' => $this->faker->sentence(),
            'evidence' => null,
            'confidence' => $this->faker->numberBetween(40, 95),
            'status' => DiaryAnalysisAlert::STATUS_PENDING,
            'teacher_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
