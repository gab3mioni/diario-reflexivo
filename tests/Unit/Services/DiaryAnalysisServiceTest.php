<?php

use App\Models\AiProviderConfig;
use App\Models\AnalysisPrompt;
use App\Models\DiaryAnalysis;
use App\Models\LessonResponse;
use App\Services\DiaryAnalysisService;
use Illuminate\Support\Facades\Bus;

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

test('requestAnalysis pins to active_version_id even when a newer version exists', function () {
    Bus::fake();

    $prompt = AnalysisPrompt::where('slug', 'diary-analysis')->firstOrFail();
    $v1 = $prompt->versions()->reorder('version')->first();
    $latest = $prompt->versions()->first();

    expect($latest->version)->toBeGreaterThan($v1->version);

    $prompt->update(['active_version_id' => $v1->id]);

    AiProviderConfig::factory()->create(['is_active' => true]);
    $response = LessonResponse::factory()->create();

    $analysis = $this->service->requestAnalysis($response);

    expect($analysis->prompt_version_id)->toBe($v1->id)
        ->and($analysis->prompt_version_id)->not->toBe($latest->id);
});

test('requestAnalysis falls back to latest version when no active pin', function () {
    Bus::fake();

    $prompt = AnalysisPrompt::where('slug', 'diary-analysis')->firstOrFail();
    $v2 = $prompt->versions()->orderByDesc('version')->first();

    AiProviderConfig::factory()->create(['is_active' => true]);
    $response = LessonResponse::factory()->create();

    $analysis = $this->service->requestAnalysis($response);

    expect($analysis->prompt_version_id)->toBe($v2->id);
});
