<?php

use App\Models\DiaryAnalysis;
use App\Models\LessonResponse;
use App\Services\DiaryAnalysisService;

beforeEach(function () {
    $this->service = app(DiaryAnalysisService::class);
});

test('canRequestAnalysis allows when no previous analyses exist', function () {
    $response = LessonResponse::factory()->create();

    expect($this->service->canRequestAnalysis($response->id))->toBeTrue();
});

test('canRequestAnalysis blocks after 3 analyses in the last 24h', function () {
    $response = LessonResponse::factory()->create();
    DiaryAnalysis::factory()->count(3)->create([
        'lesson_response_id' => $response->id,
        'created_at' => now()->subHours(2),
    ]);

    expect($this->service->canRequestAnalysis($response->id))->toBeFalse();
});

test('canRequestAnalysis allows again after the 24h window rolls', function () {
    $response = LessonResponse::factory()->create();

    // 3 analyses 25 hours ago — outside the window
    DiaryAnalysis::factory()->count(3)->create([
        'lesson_response_id' => $response->id,
        'created_at' => now()->subHours(25),
    ]);

    expect($this->service->canRequestAnalysis($response->id))->toBeTrue();
});

test('canRequestAnalysis allows on the 3rd request within window', function () {
    $response = LessonResponse::factory()->create();
    DiaryAnalysis::factory()->count(2)->create([
        'lesson_response_id' => $response->id,
        'created_at' => now()->subHour(),
    ]);

    expect($this->service->canRequestAnalysis($response->id))->toBeTrue();
});

test('canRequestAnalysis only counts analyses for the given response', function () {
    $a = LessonResponse::factory()->create();
    $b = LessonResponse::factory()->create();

    DiaryAnalysis::factory()->count(3)->create([
        'lesson_response_id' => $b->id,
        'created_at' => now(),
    ]);

    expect($this->service->canRequestAnalysis($a->id))->toBeTrue();
    expect($this->service->canRequestAnalysis($b->id))->toBeFalse();
});
