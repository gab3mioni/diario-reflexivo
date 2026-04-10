<?php

use App\Contracts\Chat\BranchClassifierContract;
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
    Queue::fake(); // DiaryAnalysisService dispatches AnalyzeDiaryResponse

    $this->fake = new FakeBranchClassifier();
    $this->app->instance(BranchClassifierContract::class, $this->fake);

    $this->processor = app(ChatTurnProcessor::class);
    $this->response = LessonResponse::factory()->create();
});

function chatMessageFor(LessonResponse $response, string $nodeId, string $content): ChatMessage
{
    return ChatMessage::factory()->student()->create([
        'lesson_response_id' => $response->id,
        'node_id' => $nodeId,
        'content' => $content,
    ]);
}

test('openingTurn posts start message and advances to first question', function () {
    $script = QuestionScript::factory()->create();

    $this->processor->openingTurn($script, $this->response);

    $messages = $this->response->chatMessages()->get();
    expect($messages)->toHaveCount(2); // start + q1
    expect($messages[0]->node_id)->toBe('start');
    expect($messages[1]->node_id)->toBe('q1');
});

test('processStudentTurn at or above global cap creates turn_cap alert and finalizes', function () {
    $script = QuestionScript::factory()->create();
    $this->response->update(['student_message_count' => ChatTurnProcessor::GLOBAL_MESSAGE_CAP]);

    $studentMsg = chatMessageFor($this->response, 'q1', 'last answer');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $studentMsg);

    $alerts = ResponseAlert::where('lesson_response_id', $this->response->id)->get();
    expect($alerts->pluck('type'))->toContain(ResponseAlert::TYPE_TURN_CAP);

    expect($this->response->fresh()->submitted_at)->not->toBeNull();
});

test('processStudentTurn with unknown node finalizes gracefully', function () {
    $script = QuestionScript::factory()->create();

    $studentMsg = chatMessageFor($this->response, 'ghost-node', 'hi');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $studentMsg);

    expect($this->response->fresh()->submitted_at)->not->toBeNull();
});

test('processStudentTurn in single-edge question skips classifier and advances', function () {
    $script = QuestionScript::factory()->create();
    $studentMsg = chatMessageFor($this->response, 'q1', 'minha reflexão');

    $this->processor->processStudentTurn($script, $this->response->fresh(), $studentMsg);

    expect($this->fake->branchCalls)->toBe(0);
    // q1 only has edge to 'end' → which triggers final_check sentinel.
    $lastBot = $this->response->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->node_id)->toBe(ChatTurnProcessor::SENTINEL_FINAL_CHECK);
    expect($this->response->fresh()->awaiting_final_check)->toBeTrue();
});

test('processStudentTurn on free_text branching asks classifier and follows its choice', function () {
    $script = QuestionScript::factory()->withBranching()->create();
    $this->fake->nextBranchResult = 'e1'; // → positive

    $studentMsg = chatMessageFor($this->response, 'q1', 'foi ótimo');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $studentMsg);

    expect($this->fake->branchCalls)->toBe(1);

    $lastBot = $this->response->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->node_id)->toBe('positive');
    expect($lastBot->classifier_status)->toBe('ok');
});

test('processStudentTurn falls back to default edge when classifier fails', function () {
    $script = QuestionScript::factory()->withBranching()->create();
    $this->fake->shouldThrow = true;

    $studentMsg = chatMessageFor($this->response, 'q1', 'inconclusivo');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $studentMsg);

    // default edge in the branching fixture targets 'end' → final_check
    $lastBot = $this->response->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->node_id)->toBe(ChatTurnProcessor::SENTINEL_FINAL_CHECK);
});

test('final_check sentinel with classifier exit finalizes the response', function () {
    $script = QuestionScript::factory()->create();
    $this->response->update(['awaiting_final_check' => true]);
    $this->fake->nextContinuationResult = 'exit';

    $studentMsg = chatMessageFor($this->response, ChatTurnProcessor::SENTINEL_FINAL_CHECK, 'não, obrigado');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $studentMsg);

    expect($this->response->fresh()->submitted_at)->not->toBeNull();
    expect($this->response->fresh()->awaiting_final_check)->toBeFalse();
});

test('final_check sentinel with classifier continue opens final_talk loop', function () {
    $script = QuestionScript::factory()->create();
    $this->response->update(['awaiting_final_check' => true]);
    $this->fake->nextContinuationResult = 'continue';

    $studentMsg = chatMessageFor($this->response, ChatTurnProcessor::SENTINEL_FINAL_CHECK, 'sim, quero falar mais');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $studentMsg);

    expect($this->response->fresh()->submitted_at)->toBeNull();
    $lastBot = $this->response->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->node_id)->toBe(ChatTurnProcessor::SENTINEL_FINAL_TALK);
});

test('final_talk hitting max turns forces finalize', function () {
    $script = QuestionScript::factory()->create();
    $this->response->update(['free_talk_turn_count' => ChatTurnProcessor::FINAL_TALK_MAX_TURNS - 1]);
    $this->fake->nextContinuationResult = 'continue'; // would continue, but cap hits

    $studentMsg = chatMessageFor($this->response, ChatTurnProcessor::SENTINEL_FINAL_TALK, 'mais coisa');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $studentMsg);

    expect($this->response->fresh()->submitted_at)->not->toBeNull();
});

test('consolidateResponse writes concatenated content on finalize', function () {
    $script = QuestionScript::factory()->create();

    // Simulate a conversation: bot q1 + student answer to q1
    ChatMessage::factory()->bot()->create([
        'lesson_response_id' => $this->response->id,
        'node_id' => 'q1',
        'content' => 'Como foi seu dia?',
    ]);
    ChatMessage::factory()->student()->create([
        'lesson_response_id' => $this->response->id,
        'node_id' => 'q1',
        'content' => 'Foi produtivo.',
    ]);

    // Force finalize via cap
    $this->response->update(['student_message_count' => ChatTurnProcessor::GLOBAL_MESSAGE_CAP]);
    $studentMsg = chatMessageFor($this->response, 'q1', 'extra');

    $this->processor->processStudentTurn($script, $this->response->fresh(), $studentMsg);

    $fresh = $this->response->fresh();
    expect($fresh->submitted_at)->not->toBeNull();
    expect($fresh->content)->toContain('Como foi seu dia?');
    expect($fresh->content)->toContain('Foi produtivo.');
});
