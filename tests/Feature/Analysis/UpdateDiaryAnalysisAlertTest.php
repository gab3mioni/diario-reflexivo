<?php

use App\Models\DiaryAnalysis;
use App\Models\DiaryAnalysisAlert;
use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->teacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();

    $this->subject = Subject::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->lesson = Lesson::factory()->create(['subject_id' => $this->subject->id]);
    $this->response = LessonResponse::factory()->create([
        'lesson_id' => $this->lesson->id,
        'student_id' => $this->student->id,
    ]);
    $this->analysis = DiaryAnalysis::factory()->create([
        'lesson_response_id' => $this->response->id,
    ]);
    $this->alert = DiaryAnalysisAlert::factory()->create([
        'diary_analysis_id' => $this->analysis->id,
        'lesson_response_id' => $this->response->id,
        'status' => DiaryAnalysisAlert::STATUS_PENDING,
    ]);
});

function triageAlert(LessonResponse $response, DiaryAnalysisAlert $alert, array $payload)
{
    return from(route('diaries.show', $response))
        ->patch(route('diaries.alerts.update', [$response, $alert]), $payload);
}

it('lets the owning teacher acknowledge an alert and records the review', function () {
    actingAs($this->teacher);

    triageAlert($this->response, $this->alert, [
        'status' => DiaryAnalysisAlert::STATUS_ACKNOWLEDGED,
    ])->assertRedirect(route('diaries.show', $this->response));

    $this->alert->refresh();

    expect($this->alert->status)->toBe(DiaryAnalysisAlert::STATUS_ACKNOWLEDGED)
        ->and($this->alert->reviewed_by)->toBe($this->teacher->id)
        ->and($this->alert->reviewed_at)->not->toBeNull();
});

it('lets the owning teacher dismiss an alert', function () {
    actingAs($this->teacher);

    triageAlert($this->response, $this->alert, [
        'status' => DiaryAnalysisAlert::STATUS_DISMISSED,
    ])->assertRedirect();

    $this->alert->refresh();

    expect($this->alert->status)->toBe(DiaryAnalysisAlert::STATUS_DISMISSED)
        ->and($this->alert->reviewed_by)->toBe($this->teacher->id);
});

it('clears the review when reverting an alert to pending', function () {
    $this->alert->update([
        'status' => DiaryAnalysisAlert::STATUS_ACKNOWLEDGED,
        'reviewed_by' => $this->teacher->id,
        'reviewed_at' => now(),
    ]);

    actingAs($this->teacher);

    triageAlert($this->response, $this->alert, [
        'status' => DiaryAnalysisAlert::STATUS_PENDING,
    ])->assertRedirect();

    $this->alert->refresh();

    expect($this->alert->status)->toBe(DiaryAnalysisAlert::STATUS_PENDING)
        ->and($this->alert->reviewed_by)->toBeNull()
        ->and($this->alert->reviewed_at)->toBeNull();
});

it('keeps the teacher note when reopening an alert', function () {
    $this->alert->update([
        'status' => DiaryAnalysisAlert::STATUS_ACKNOWLEDGED,
        'teacher_note' => 'Conversei com o aluno em sala.',
        'reviewed_by' => $this->teacher->id,
        'reviewed_at' => now(),
    ]);

    actingAs($this->teacher);

    triageAlert($this->response, $this->alert, [
        'status' => DiaryAnalysisAlert::STATUS_PENDING,
        'teacher_note' => 'Conversei com o aluno em sala.',
    ])->assertRedirect();

    $this->alert->refresh();

    expect($this->alert->status)->toBe(DiaryAnalysisAlert::STATUS_PENDING)
        ->and($this->alert->reviewed_by)->toBeNull()
        ->and($this->alert->reviewed_at)->toBeNull()
        ->and($this->alert->teacher_note)->toBe('Conversei com o aluno em sala.');
});

it('forbids a teacher who does not own the lesson', function () {
    $otherTeacher = User::factory()->teacher()->create();

    actingAs($otherTeacher);

    triageAlert($this->response, $this->alert, [
        'status' => DiaryAnalysisAlert::STATUS_ACKNOWLEDGED,
    ])->assertForbidden();

    expect($this->alert->fresh()->status)->toBe(DiaryAnalysisAlert::STATUS_PENDING);
});

it('rejects an unknown status', function () {
    actingAs($this->teacher);

    triageAlert($this->response, $this->alert, [
        'status' => 'snoozed',
    ])->assertSessionHasErrors('status');

    expect($this->alert->fresh()->status)->toBe(DiaryAnalysisAlert::STATUS_PENDING);
});

it('does not let the route act on an alert from another response', function () {
    $otherStudent = User::factory()->student()->create();
    $otherResponse = LessonResponse::factory()->create([
        'lesson_id' => $this->lesson->id,
        'student_id' => $otherStudent->id,
    ]);
    $otherAnalysis = DiaryAnalysis::factory()->create([
        'lesson_response_id' => $otherResponse->id,
    ]);
    $foreignAlert = DiaryAnalysisAlert::factory()->create([
        'diary_analysis_id' => $otherAnalysis->id,
        'lesson_response_id' => $otherResponse->id,
        'status' => DiaryAnalysisAlert::STATUS_PENDING,
    ]);

    actingAs($this->teacher);

    triageAlert($this->response, $foreignAlert, [
        'status' => DiaryAnalysisAlert::STATUS_ACKNOWLEDGED,
    ])->assertNotFound();

    expect($foreignAlert->fresh()->status)->toBe(DiaryAnalysisAlert::STATUS_PENDING);
});

it('stores the teacher note when triaging an alert', function () {
    actingAs($this->teacher);

    triageAlert($this->response, $this->alert, [
        'status' => DiaryAnalysisAlert::STATUS_ACKNOWLEDGED,
        'teacher_note' => 'Conversei com o aluno em sala.',
    ])->assertRedirect();

    expect($this->alert->fresh()->teacher_note)->toBe('Conversei com o aluno em sala.');
});

it('requires a note when dismissing a socioemotional alert', function () {
    $socio = DiaryAnalysisAlert::factory()->create([
        'diary_analysis_id' => $this->analysis->id,
        'lesson_response_id' => $this->response->id,
        'type' => DiaryAnalysisAlert::TYPE_SOCIOEMOTIONAL,
        'status' => DiaryAnalysisAlert::STATUS_PENDING,
    ]);

    actingAs($this->teacher);

    triageAlert($this->response, $socio, [
        'status' => DiaryAnalysisAlert::STATUS_DISMISSED,
    ])->assertSessionHasErrors('teacher_note');

    expect($socio->fresh()->status)->toBe(DiaryAnalysisAlert::STATUS_PENDING);
});

it('dismisses a socioemotional alert when a note is given', function () {
    $socio = DiaryAnalysisAlert::factory()->create([
        'diary_analysis_id' => $this->analysis->id,
        'lesson_response_id' => $this->response->id,
        'type' => DiaryAnalysisAlert::TYPE_SOCIOEMOTIONAL,
        'status' => DiaryAnalysisAlert::STATUS_PENDING,
    ]);

    actingAs($this->teacher);

    triageAlert($this->response, $socio, [
        'status' => DiaryAnalysisAlert::STATUS_DISMISSED,
        'teacher_note' => 'Encaminhei ao apoio psicopedagógico.',
    ])->assertRedirect();

    expect($socio->fresh()->status)->toBe(DiaryAnalysisAlert::STATUS_DISMISSED);
});
