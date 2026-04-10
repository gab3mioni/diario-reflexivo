<?php

use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\ResponseAlert;
use App\Models\Subject;
use App\Models\User;

beforeEach(function () {
    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->admin = User::factory()->admin()->create();

    $this->subject = Subject::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->lesson = Lesson::factory()->create(['subject_id' => $this->subject->id]);
    $this->response = LessonResponse::factory()->create([
        'lesson_id' => $this->lesson->id,
        'student_id' => $this->student->id,
    ]);
    $this->alert = ResponseAlert::factory()->create([
        'lesson_response_id' => $this->response->id,
    ]);
});

test('subject teacher can view alerts', function () {
    expect($this->teacher->can('view', $this->alert))->toBeTrue();
    expect($this->teacher->can('markAsRead', $this->alert))->toBeTrue();
});

test('admin can view and mark alerts', function () {
    expect($this->admin->can('view', $this->alert))->toBeTrue();
});

test('IDOR: other teacher cannot view alerts from another subject', function () {
    expect($this->otherTeacher->can('view', $this->alert))->toBeFalse();
});

test('student — even the owner of the response — cannot view alerts about themselves', function () {
    expect($this->student->can('view', $this->alert))->toBeFalse();
});
