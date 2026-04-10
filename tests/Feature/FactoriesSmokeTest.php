<?php

use App\Models\ChatMessage;
use App\Models\LessonResponse;
use App\Models\QuestionScript;
use App\Models\ResponseAlert;
use App\Models\User;

test('UserFactory student state creates user with student role', function () {
    $user = User::factory()->student()->create();

    expect($user->isStudent())->toBeTrue();
    expect($user->isTeacher())->toBeFalse();
    expect($user->isAdmin())->toBeFalse();
});

test('UserFactory teacher state creates user with teacher role', function () {
    $user = User::factory()->teacher()->create();

    expect($user->isTeacher())->toBeTrue();
    expect($user->isStudent())->toBeFalse();
});

test('UserFactory admin state creates user with admin role', function () {
    $user = User::factory()->admin()->create();

    expect($user->isAdmin())->toBeTrue();
});

test('ChatMessageFactory bot state creates bot message', function () {
    $message = ChatMessage::factory()->bot()->create();

    expect($message->role)->toBe('bot');
    expect($message->lesson_response_id)->not->toBeNull();
});

test('ChatMessageFactory student state creates student message', function () {
    $message = ChatMessage::factory()->student()->create();

    expect($message->role)->toBe('student');
});

test('ChatMessageFactory classified state sets classifier status', function () {
    $message = ChatMessage::factory()->classified('failed', 'provider down')->create();

    expect($message->classifier_status)->toBe('failed');
    expect($message->classifier_reason)->toBe('provider down');
});

test('QuestionScriptFactory creates minimal valid graph', function () {
    $script = QuestionScript::factory()->create();

    $nodes = collect($script->nodes);
    expect($nodes->where('type', 'start')->count())->toBe(1);
    expect($nodes->where('type', 'end')->count())->toBe(1);
    expect($nodes->where('type', 'question')->count())->toBeGreaterThanOrEqual(1);

    $edges = collect($script->edges);
    expect($edges->count())->toBeGreaterThanOrEqual(2);
});

test('QuestionScriptFactory active state activates the script', function () {
    $script = QuestionScript::factory()->active()->create();

    expect($script->is_active)->toBeTrue();
});

test('QuestionScriptFactory withBranching provides multiple outgoing edges', function () {
    $script = QuestionScript::factory()->withBranching()->create();

    $edgesFromQ1 = $script->getOutgoingEdges('q1');
    expect(count($edgesFromQ1))->toBeGreaterThanOrEqual(3);

    $default = $script->getDefaultOutgoingEdge('q1');
    expect($default)->not->toBeNull();
});

test('ResponseAlertFactory creates alert linked to response', function () {
    $alert = ResponseAlert::factory()->create();

    expect($alert->lessonResponse)->toBeInstanceOf(LessonResponse::class);
    expect($alert->type)->toBe(ResponseAlert::TYPE_ABSENCE);
});
