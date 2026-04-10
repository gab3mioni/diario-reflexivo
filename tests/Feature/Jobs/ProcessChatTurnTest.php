<?php

use App\Contracts\Chat\BranchClassifierContract;
use App\Jobs\ProcessChatTurn;
use App\Models\ChatMessage;
use App\Models\LessonResponse;
use App\Models\QuestionScript;
use App\Models\ResponseAlert;
use App\Services\Chat\ChatTurnProcessor;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeBranchClassifier;

beforeEach(function () {
    Notification::fake();
    Queue::fake([\App\Jobs\AnalyzeDiaryResponse::class]);

    $this->fake = new FakeBranchClassifier();
    $this->app->instance(BranchClassifierContract::class, $this->fake);
});

test('handle() resets chat_state to idle after processing', function () {
    QuestionScript::factory()->active()->create();
    $response = LessonResponse::factory()->create([
        'chat_state' => LessonResponse::CHAT_STATE_PROCESSING,
        'chat_state_since' => now(),
    ]);
    $msg = ChatMessage::factory()->student()->create([
        'lesson_response_id' => $response->id,
        'node_id' => 'q1',
    ]);

    (new ProcessChatTurn($response->id, $msg->id))
        ->handle(app(ChatTurnProcessor::class));

    expect($response->fresh()->chat_state)->toBe(LessonResponse::CHAT_STATE_IDLE);
});

test('handle() is a no-op when no active script exists and still resets state', function () {
    QuestionScript::query()->delete();
    $response = LessonResponse::factory()->create([
        'chat_state' => LessonResponse::CHAT_STATE_PROCESSING,
    ]);
    $msg = ChatMessage::factory()->student()->create(['lesson_response_id' => $response->id]);

    (new ProcessChatTurn($response->id, $msg->id))->handle(app(ChatTurnProcessor::class));

    expect($response->fresh()->chat_state)->toBe(LessonResponse::CHAT_STATE_IDLE);
});

test('failed() creates classifier_failure alert and resets state', function () {
    $response = LessonResponse::factory()->create([
        'chat_state' => LessonResponse::CHAT_STATE_PROCESSING,
        'chat_state_since' => now(),
    ]);

    $job = new ProcessChatTurn($response->id, 999999);
    $job->failed(new Exception('boom'));

    $alert = ResponseAlert::where('lesson_response_id', $response->id)->first();
    expect($alert)->not->toBeNull();
    expect($alert->type)->toBe(ResponseAlert::TYPE_CLASSIFIER_FAILURE);
    expect($alert->severity)->toBe(ResponseAlert::SEVERITY_MEDIUM);

    expect($response->fresh()->chat_state)->toBe(LessonResponse::CHAT_STATE_IDLE);
});

test('handle() resets state even if processor throws', function () {
    QuestionScript::factory()->active()->withBranching()->create();
    $response = LessonResponse::factory()->create([
        'chat_state' => LessonResponse::CHAT_STATE_PROCESSING,
    ]);
    $msg = ChatMessage::factory()->student()->create([
        'lesson_response_id' => $response->id,
        'node_id' => 'q1',
    ]);

    $this->fake->shouldThrow = true;
    // The processor catches BranchClassifierException internally and falls back,
    // so handle() completes normally — state should reset.
    (new ProcessChatTurn($response->id, $msg->id))
        ->handle(app(ChatTurnProcessor::class));

    expect($response->fresh()->chat_state)->toBe(LessonResponse::CHAT_STATE_IDLE);
});
