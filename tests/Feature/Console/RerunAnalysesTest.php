<?php

use App\Jobs\AnalyzeDiaryResponse;
use App\Models\AiProviderConfig;
use App\Models\DiaryAnalysis;
use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    AiProviderConfig::factory()->create(['is_active' => true]);

    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->create(['teacher_id' => $teacher->id]);
    $this->lesson = Lesson::factory()->create(['subject_id' => $subject->id]);
});

function submittedResponse(Lesson $lesson): LessonResponse
{
    return LessonResponse::factory()->create([
        'lesson_id' => $lesson->id,
        'student_id' => User::factory()->student()->create()->id,
        'submitted_at' => now(),
    ]);
}

test('rerun dispatches analysis for submitted responses of a lesson', function () {
    Queue::fake();

    submittedResponse($this->lesson);
    submittedResponse($this->lesson);

    $this->artisan('analyses:rerun', ['--lesson' => $this->lesson->id])->assertSuccessful();

    Queue::assertPushed(AnalyzeDiaryResponse::class, 2);
});

test('rerun skips responses that already have a pending analysis', function () {
    Queue::fake();

    $response = submittedResponse($this->lesson);
    DiaryAnalysis::factory()->create([
        'lesson_response_id' => $response->id,
        'status' => DiaryAnalysis::STATUS_PENDING,
    ]);

    $this->artisan('analyses:rerun', ['--lesson' => $this->lesson->id])->assertSuccessful();

    Queue::assertNothingPushed();
});

test('rerun with --failed targets responses whose analysis failed', function () {
    Queue::fake();

    $response = submittedResponse($this->lesson);
    DiaryAnalysis::factory()->create([
        'lesson_response_id' => $response->id,
        'status' => DiaryAnalysis::STATUS_FAILED,
    ]);

    $this->artisan('analyses:rerun', ['--failed' => true])->assertSuccessful();

    Queue::assertPushed(AnalyzeDiaryResponse::class, 1);
});

test('rerun with --failed ignores responses recovered by a later analysis', function () {
    Queue::fake();

    $response = submittedResponse($this->lesson);
    DiaryAnalysis::factory()->create([
        'lesson_response_id' => $response->id,
        'status' => DiaryAnalysis::STATUS_FAILED,
    ]);
    DiaryAnalysis::factory()->create([
        'lesson_response_id' => $response->id,
        'status' => DiaryAnalysis::STATUS_COMPLETED,
    ]);

    $this->artisan('analyses:rerun', ['--failed' => true])->assertSuccessful();

    Queue::assertNothingPushed();
});

test('rerun dry-run dispatches nothing', function () {
    Queue::fake();

    submittedResponse($this->lesson);

    $this->artisan('analyses:rerun', ['--lesson' => $this->lesson->id, '--dry-run' => true])->assertSuccessful();

    Queue::assertNothingPushed();
});

test('rerun fails when no scope is given', function () {
    $this->artisan('analyses:rerun')->assertFailed();
});
