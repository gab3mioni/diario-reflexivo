<?php

use App\Models\Lesson;
use App\Models\Subject;
use App\Models\User;

beforeEach(function () {
    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->otherStudent = User::factory()->student()->create();
    $this->admin = User::factory()->admin()->create();

    $this->subject = Subject::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->subject->students()->attach($this->student->id);
    $this->lesson = Lesson::factory()->create(['subject_id' => $this->subject->id]);
});

test('subject teacher can view their lesson', function () {
    expect($this->teacher->can('view', $this->lesson))->toBeTrue();
});

test('enrolled student can view the lesson', function () {
    expect($this->student->can('view', $this->lesson))->toBeTrue();
});

test('admin bypass: admin can view any lesson', function () {
    expect($this->admin->can('view', $this->lesson))->toBeTrue();
});

test('IDOR: other teacher cannot view the lesson', function () {
    expect($this->otherTeacher->can('view', $this->lesson))->toBeFalse();
});

test('IDOR: non-enrolled student cannot view the lesson', function () {
    expect($this->otherStudent->can('view', $this->lesson))->toBeFalse();
});

test('only owner teacher can update/delete', function () {
    expect($this->teacher->can('update', $this->lesson))->toBeTrue();
    expect($this->teacher->can('delete', $this->lesson))->toBeTrue();

    expect($this->otherTeacher->can('update', $this->lesson))->toBeFalse();
    expect($this->otherTeacher->can('delete', $this->lesson))->toBeFalse();

    expect($this->student->can('update', $this->lesson))->toBeFalse();
});

test('admin can update/delete any lesson', function () {
    expect($this->admin->can('update', $this->lesson))->toBeTrue();
    expect($this->admin->can('delete', $this->lesson))->toBeTrue();
});
