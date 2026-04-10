<?php

use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\ResponseAlert;
use App\Models\Subject;
use App\Models\User;

test('student dashboard renders with stats', function () {
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $subject->students()->attach($student->id);
    Lesson::factory()->count(2)->create(['subject_id' => $subject->id]);

    $this->actingAs($student);
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard')
            ->where('dashboardRole', 'student')
            ->has('stats.pending_count')
        );
});

test('teacher dashboard returns alert aggregates', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->create(['teacher_id' => $teacher->id]);
    $lesson = Lesson::factory()->create(['subject_id' => $subject->id]);
    $response = LessonResponse::factory()->create(['lesson_id' => $lesson->id]);

    ResponseAlert::factory()->create([
        'lesson_response_id' => $response->id,
        'severity' => 'high',
    ]);
    ResponseAlert::factory()->create([
        'lesson_response_id' => $response->id,
        'severity' => 'low',
    ]);

    $this->actingAs($teacher);
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('stats.unread_alerts', 2)
            ->where('stats.high_severity_alerts', 1)
        );
});

test('admin dashboard returns admin stats', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('dashboardRole', 'admin')
            ->has('stats.total_users')
        );
});
