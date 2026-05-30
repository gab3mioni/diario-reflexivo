<?php

use App\Models\ClassGroup;
use App\Models\DiaryAnalysis;
use App\Models\DiaryAnalysisAlert;
use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->teacher = User::factory()->create();
    $this->classGroup = ClassGroup::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->lesson = Lesson::factory()->create([
        'teacher_id' => $this->teacher->id,
        'class_group_id' => $this->classGroup->id,
    ]);
    $this->response = LessonResponse::factory()->create([
        'lesson_id' => $this->lesson->id,
    ]);
    $this->analysis = DiaryAnalysis::factory()->create([
        'lesson_response_id' => $this->response->id,
        'status' => DiaryAnalysis::STATUS_COMPLETED,
    ]);
    $this->alert = DiaryAnalysisAlert::factory()->create([
        'diary_analysis_id' => $this->analysis->id,
        'status' => DiaryAnalysisAlert::STATUS_PENDING,
    ]);
});

function updateAlert(Lesson $lesson, LessonResponse $response, DiaryAnalysisAlert $alert, array $payload)
{
    return from(route('lesson-responses.show', [$lesson, $response]))
        ->patch(route('lesson-responses.alerts.update', [$lesson, $response, $alert]), $payload);
}

it('lets the owning teacher acknowledge an alert and records the triage', function () {
    actingAs($this->teacher);

    updateAlert($this->lesson, $this->response, $this->alert, [
        'status' => DiaryAnalysisAlert::STATUS_ACKNOWLEDGED,
    ])->assertRedirect(route('lesson-responses.show', [$this->lesson, $this->response]));

    $this->alert->refresh();

    expect($this->alert->status)->toBe(DiaryAnalysisAlert::STATUS_ACKNOWLEDGED)
        ->and($this->alert->acknowledged_by)->toBe($this->teacher->id)
        ->and($this->alert->acknowledged_at)->not->toBeNull();
});

it('lets the owning teacher dismiss an alert', function () {
    actingAs($this->teacher);

    updateAlert($this->lesson, $this->response, $this->alert, [
        'status' => DiaryAnalysisAlert::STATUS_DISMISSED,
    ])->assertRedirect();

    $this->alert->refresh();

    expect($this->alert->status)->toBe(DiaryAnalysisAlert::STATUS_DISMISSED)
        ->and($this->alert->acknowledged_by)->toBe($this->teacher->id);
});

it('clears the triage when reverting an alert to pending', function () {
    $this->alert->update([
        'status' => DiaryAnalysisAlert::STATUS_ACKNOWLEDGED,
        'acknowledged_by' => $this->teacher->id,
        'acknowledged_at' => now(),
    ]);

    actingAs($this->teacher);

    updateAlert($this->lesson, $this->response, $this->alert, [
        'status' => DiaryAnalysisAlert::STATUS_PENDING,
    ])->assertRedirect();

    $this->alert->refresh();

    expect($this->alert->status)->toBe(DiaryAnalysisAlert::STATUS_PENDING)
        ->and($this->alert->acknowledged_by)->toBeNull()
        ->and($this->alert->acknowledged_at)->toBeNull();
});

it('forbids a teacher who does not own the lesson', function () {
    $otherTeacher = User::factory()->create();

    actingAs($otherTeacher);

    updateAlert($this->lesson, $this->response, $this->alert, [
        'status' => DiaryAnalysisAlert::STATUS_ACKNOWLEDGED,
    ])->assertForbidden();

    expect($this->alert->fresh()->status)->toBe(DiaryAnalysisAlert::STATUS_PENDING);
});

it('rejects an unknown status', function () {
    actingAs($this->teacher);

    updateAlert($this->lesson, $this->response, $this->alert, [
        'status' => 'snoozed',
    ])->assertSessionHasErrors('status');

    expect($this->alert->fresh()->status)->toBe(DiaryAnalysisAlert::STATUS_PENDING);
});

it('does not let the route act on an alert from another response', function () {
    $otherResponse = LessonResponse::factory()->create(['lesson_id' => $this->lesson->id]);
    $otherAnalysis = DiaryAnalysis::factory()->create([
        'lesson_response_id' => $otherResponse->id,
        'status' => DiaryAnalysis::STATUS_COMPLETED,
    ]);
    $foreignAlert = DiaryAnalysisAlert::factory()->create([
        'diary_analysis_id' => $otherAnalysis->id,
        'status' => DiaryAnalysisAlert::STATUS_PENDING,
    ]);

    actingAs($this->teacher);

    updateAlert($this->lesson, $this->response, $foreignAlert, [
        'status' => DiaryAnalysisAlert::STATUS_ACKNOWLEDGED,
    ])->assertNotFound();

    expect($foreignAlert->fresh()->status)->toBe(DiaryAnalysisAlert::STATUS_PENDING);
});
