<?php

use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\ResponseAlert;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\ResponseAlertRaised;
use App\Services\ResponseAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('notifies the teacher of the subject when an alert is raised', function () {
    Notification::fake();

    $teacher = User::factory()->create();
    $student = User::factory()->create();
    $subject = Subject::factory()->create(['teacher_id' => $teacher->id]);
    $lesson = Lesson::factory()->create(['subject_id' => $subject->id]);
    $response = LessonResponse::factory()->create([
        'lesson_id' => $lesson->id,
        'student_id' => $student->id,
    ]);

    app(ResponseAlertService::class)->raise(
        $response,
        ResponseAlert::TYPE_RISK_SIGNAL,
        ResponseAlert::SEVERITY_HIGH,
        'concerning content detected',
    );

    Notification::assertSentTo(
        $teacher,
        ResponseAlertRaised::class,
        fn (ResponseAlertRaised $n) => $n->alert->severity === ResponseAlert::SEVERITY_HIGH
            && $n->alert->type === ResponseAlert::TYPE_RISK_SIGNAL,
    );
});

it('persists a database notification with the alert payload', function () {
    Notification::fake();

    $teacher = User::factory()->create();
    $student = User::factory()->create();
    $subject = Subject::factory()->create(['teacher_id' => $teacher->id]);
    $lesson = Lesson::factory()->create(['subject_id' => $subject->id]);
    $response = LessonResponse::factory()->create([
        'lesson_id' => $lesson->id,
        'student_id' => $student->id,
    ]);

    app(ResponseAlertService::class)->raise(
        $response,
        ResponseAlert::TYPE_TURN_CAP,
        ResponseAlert::SEVERITY_LOW,
        'cap reached',
    );

    Notification::assertSentTo($teacher, ResponseAlertRaised::class, function (ResponseAlertRaised $n) {
        $payload = $n->toDatabase($n);

        return $payload['type'] === ResponseAlert::TYPE_TURN_CAP
            && $payload['severity'] === ResponseAlert::SEVERITY_LOW
            && $payload['reason'] === 'cap reached';
    });
});
