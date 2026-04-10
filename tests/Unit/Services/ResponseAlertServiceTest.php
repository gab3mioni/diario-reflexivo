<?php

use App\Models\LessonResponse;
use App\Models\ResponseAlert;
use App\Models\User;
use App\Services\ResponseAlertService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->service = app(ResponseAlertService::class);
});

test('raise creates alert with baseline severity for non-absence type', function () {
    $response = LessonResponse::factory()->create();

    $alert = $this->service->raise(
        $response,
        ResponseAlert::TYPE_TURN_CAP,
        ResponseAlert::SEVERITY_MEDIUM,
        'reason text',
    );

    expect($alert->type)->toBe(ResponseAlert::TYPE_TURN_CAP);
    expect($alert->severity)->toBe(ResponseAlert::SEVERITY_MEDIUM);
    expect($alert->reason)->toBe('reason text');
});

test('raise truncates reason to 500 chars', function () {
    $response = LessonResponse::factory()->create();
    $longReason = str_repeat('a', 700);

    $alert = $this->service->raise(
        $response,
        ResponseAlert::TYPE_CLASSIFIER_FAILURE,
        ResponseAlert::SEVERITY_LOW,
        $longReason,
    );

    expect(mb_strlen($alert->reason))->toBe(500);
});

test('raise accepts null reason', function () {
    $response = LessonResponse::factory()->create();

    $alert = $this->service->raise($response, ResponseAlert::TYPE_RISK_SIGNAL);

    expect($alert->reason)->toBeNull();
});

test('absence without prior absences gets LOW severity regardless of input', function () {
    $student = User::factory()->student()->create();
    $response = LessonResponse::factory()->for($student, 'student')->create();

    $alert = $this->service->raise(
        $response,
        ResponseAlert::TYPE_ABSENCE,
        ResponseAlert::SEVERITY_HIGH, // ignored: escalation rule
    );

    expect($alert->severity)->toBe(ResponseAlert::SEVERITY_LOW);
});

test('absence with 1 prior absence escalates to MEDIUM', function () {
    $student = User::factory()->student()->create();

    // Prior response with an absence alert
    $prior = LessonResponse::factory()->for($student, 'student')->create();
    ResponseAlert::factory()->for($prior, 'lessonResponse')->create([
        'type' => ResponseAlert::TYPE_ABSENCE,
    ]);

    $current = LessonResponse::factory()->for($student, 'student')->create();

    $alert = $this->service->raise($current, ResponseAlert::TYPE_ABSENCE);

    expect($alert->severity)->toBe(ResponseAlert::SEVERITY_MEDIUM);
});

test('absence with 2+ prior absences escalates to HIGH', function () {
    $student = User::factory()->student()->create();

    for ($i = 0; $i < 2; $i++) {
        $prior = LessonResponse::factory()->for($student, 'student')->create();
        ResponseAlert::factory()->for($prior, 'lessonResponse')->create([
            'type' => ResponseAlert::TYPE_ABSENCE,
        ]);
    }

    $current = LessonResponse::factory()->for($student, 'student')->create();

    $alert = $this->service->raise($current, ResponseAlert::TYPE_ABSENCE);

    expect($alert->severity)->toBe(ResponseAlert::SEVERITY_HIGH);
});

test('absence escalation ignores alerts from OTHER students', function () {
    $victim = User::factory()->student()->create();
    $other = User::factory()->student()->create();

    // 3 absences for the other student — should not affect victim
    for ($i = 0; $i < 3; $i++) {
        $r = LessonResponse::factory()->for($other, 'student')->create();
        ResponseAlert::factory()->for($r, 'lessonResponse')->create([
            'type' => ResponseAlert::TYPE_ABSENCE,
        ]);
    }

    $victimResponse = LessonResponse::factory()->for($victim, 'student')->create();
    $alert = $this->service->raise($victimResponse, ResponseAlert::TYPE_ABSENCE);

    expect($alert->severity)->toBe(ResponseAlert::SEVERITY_LOW);
});
