<?php

use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\Subject;
use App\Models\User;

beforeEach(function () {
    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->otherStudent = User::factory()->student()->create();
    $this->admin = User::factory()->admin()->create();

    $this->subject = Subject::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->lesson = Lesson::factory()->create(['subject_id' => $this->subject->id]);

    $this->response = LessonResponse::factory()->create([
        'lesson_id' => $this->lesson->id,
        'student_id' => $this->student->id,
    ]);
});

test('owner student can view own response', function () {
    expect($this->student->can('view', $this->response))->toBeTrue();
});

test('subject teacher can view student response', function () {
    expect($this->teacher->can('view', $this->response))->toBeTrue();
});

test('IDOR: another student cannot view the response', function () {
    expect($this->otherStudent->can('view', $this->response))->toBeFalse();
});

test('IDOR: another teacher cannot view the response', function () {
    expect($this->otherTeacher->can('view', $this->response))->toBeFalse();
});

test('admin can view any response', function () {
    expect($this->admin->can('view', $this->response))->toBeTrue();
});

test('student can interact while not submitted', function () {
    expect($this->student->can('interact', $this->response))->toBeTrue();
});

test('student cannot interact after submission', function () {
    $this->response->update(['submitted_at' => now()]);
    expect($this->student->fresh()->can('interact', $this->response->fresh()))->toBeFalse();
});

test('teacher cannot interact (only students write)', function () {
    expect($this->teacher->can('interact', $this->response))->toBeFalse();
});

test('only subject teacher can requestAnalysis and reviewAnalysis', function () {
    expect($this->teacher->can('requestAnalysis', $this->response))->toBeTrue();
    expect($this->teacher->can('reviewAnalysis', $this->response))->toBeTrue();

    expect($this->otherTeacher->can('requestAnalysis', $this->response))->toBeFalse();
    expect($this->student->can('requestAnalysis', $this->response))->toBeFalse();
});
