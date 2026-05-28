<?php

use App\Contracts\Chat\BranchClassifierContract;
use App\Models\ChatMessage;
use App\Models\LessonResponse;
use App\Models\QuestionScript;
use App\Services\Chat\ChatTurnProcessor;
use App\Services\Chat\EngagementDecision;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeBranchClassifier;

beforeEach(function () {
    Notification::fake();
    Queue::fake();

    $this->fake = new FakeBranchClassifier;
    $this->app->instance(BranchClassifierContract::class, $this->fake);

    $this->processor = app(ChatTurnProcessor::class);
    $this->response = LessonResponse::factory()->create();
});

function studentTurnAt(LessonResponse $response, string $nodeId, string $content): ChatMessage
{
    return ChatMessage::factory()->student()->create([
        'lesson_response_id' => $response->id,
        'node_id' => $nodeId,
        'content' => $content,
    ]);
}

test('free_talk with continue decision resets streak and sends listening message', function () {
    $script = QuestionScript::factory()->withFreeTalk()->create();
    $this->response->update(['low_engagement_streak' => 1, 'free_talk_turn_count' => 0]);
    $this->fake->nextEngagementLevel = EngagementDecision::LEVEL_MEDIUM;
    $this->fake->nextEngagementDecision = EngagementDecision::DECISION_CONTINUE;

    $msg = studentTurnAt($this->response, 'ft1', 'estou bem mesmo, foi tranquilo');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $msg);

    $fresh = $this->response->fresh();
    expect($fresh->low_engagement_streak)->toBe(0);
    expect($this->fake->engagementCalls)->toBe(1);

    $lastBot = $fresh->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->node_id)->toBe('ft1');
    expect($lastBot->content)->toContain('Estou ouvindo');
});

test('free_talk with reengage decision below threshold increments streak and uses node reengagement_message', function () {
    $script = QuestionScript::factory()->withFreeTalk()->create();
    $this->response->update(['low_engagement_streak' => 0, 'free_talk_turn_count' => 0]);
    $this->fake->nextEngagementLevel = EngagementDecision::LEVEL_LOW;
    $this->fake->nextEngagementDecision = EngagementDecision::DECISION_REENGAGE;

    $msg = studentTurnAt($this->response, 'ft1', 'ok');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $msg);

    $fresh = $this->response->fresh();
    expect($fresh->low_engagement_streak)->toBe(1);

    $lastBot = $fresh->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->node_id)->toBe('ft1');
    expect($lastBot->content)->toContain('Sem pressa'); // mensagem definida no withFreeTalk
});

test('free_talk with reengage falls back to default message when node has none', function () {
    $script = QuestionScript::factory()->create([
        'nodes' => [
            ['id' => 'start', 'type' => 'start', 'data' => []],
            ['id' => 'ft1', 'type' => 'free_talk', 'data' => ['message' => 'oi', 'max_turns' => 3]],
            ['id' => 'end', 'type' => 'end', 'data' => []],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'start', 'target' => 'ft1', 'is_default' => true],
            ['id' => 'e2', 'source' => 'ft1', 'target' => 'end', 'is_default' => true],
        ],
    ]);
    $this->fake->nextEngagementDecision = EngagementDecision::DECISION_REENGAGE;

    $msg = studentTurnAt($this->response, 'ft1', 'sla');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $msg);

    $lastBot = $this->response->fresh()->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->content)->toContain('Estou aqui te ouvindo');
});

test('free_talk reengage crossing threshold triggers confirm_exit and resets streak', function () {
    $script = QuestionScript::factory()->withFreeTalk()->create();
    $this->response->update(['low_engagement_streak' => 1, 'free_talk_turn_count' => 0]);
    $this->fake->nextEngagementDecision = EngagementDecision::DECISION_REENGAGE;

    $msg = studentTurnAt($this->response, 'ft1', 'nada');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $msg);

    $fresh = $this->response->fresh();
    expect($fresh->low_engagement_streak)->toBe(0);
    expect($fresh->pending_confirm_exit_node)->toBe('ft1');

    $lastBot = $fresh->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->node_id)->toBe(ChatTurnProcessor::SENTINEL_CONFIRM_EXIT);
});

test('free_talk ask_to_end triggers confirm_exit immediately', function () {
    $script = QuestionScript::factory()->withFreeTalk()->create();
    $this->fake->nextEngagementDecision = EngagementDecision::DECISION_ASK_TO_END;

    $msg = studentTurnAt($this->response, 'ft1', 'tanto faz');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $msg);

    $fresh = $this->response->fresh();
    expect($fresh->pending_confirm_exit_node)->toBe('ft1');

    $lastBot = $fresh->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->node_id)->toBe(ChatTurnProcessor::SENTINEL_CONFIRM_EXIT);
});

test('free_talk exit decision closes free_talk and follows default edge', function () {
    $script = QuestionScript::factory()->withFreeTalk()->create();
    $this->fake->nextEngagementDecision = EngagementDecision::DECISION_EXIT;

    $msg = studentTurnAt($this->response, 'ft1', 'para de me perguntar');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $msg);

    $fresh = $this->response->fresh();
    expect($fresh->low_engagement_streak)->toBe(0);
    expect($fresh->pending_confirm_exit_node)->toBeNull();

    // Default edge ft1 → end → triggerFinalCheck
    $lastBot = $fresh->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->node_id)->toBe(ChatTurnProcessor::SENTINEL_FINAL_CHECK);
});

test('free_talk classifier failure does not change streak and sends fallback', function () {
    $script = QuestionScript::factory()->withFreeTalk()->create();
    $this->response->update(['low_engagement_streak' => 1, 'free_talk_turn_count' => 0]);
    $this->fake->shouldThrow = true;

    $msg = studentTurnAt($this->response, 'ft1', 'qualquer coisa');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $msg);

    $fresh = $this->response->fresh();
    expect($fresh->low_engagement_streak)->toBe(1); // não mexeu

    $lastBot = $fresh->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->classifier_status)->toBe('failed');
    expect($lastBot->content)->toContain('Estou ouvindo');
});

test('confirm_exit with continue resumes parent free_talk and clears pending', function () {
    $script = QuestionScript::factory()->withFreeTalk()->create();
    $this->response->update(['pending_confirm_exit_node' => 'ft1']);
    $this->fake->nextContinuationResult = 'continue';

    $msg = studentTurnAt($this->response, ChatTurnProcessor::SENTINEL_CONFIRM_EXIT, 'não, vou continuar');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $msg);

    $fresh = $this->response->fresh();
    expect($fresh->pending_confirm_exit_node)->toBeNull();
    expect($fresh->submitted_at)->toBeNull();

    $lastBot = $fresh->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->node_id)->toBe('ft1');
    expect($lastBot->content)->toContain('vamos seguir');
});

test('confirm_exit with exit closes parent free_talk via default edge', function () {
    $script = QuestionScript::factory()->withFreeTalk()->create();
    $this->response->update(['pending_confirm_exit_node' => 'ft1']);
    $this->fake->nextContinuationResult = 'exit';

    $msg = studentTurnAt($this->response, ChatTurnProcessor::SENTINEL_CONFIRM_EXIT, 'sim, pode encerrar');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $msg);

    $fresh = $this->response->fresh();
    expect($fresh->pending_confirm_exit_node)->toBeNull();

    // ft1 default edge → end → triggerFinalCheck
    $lastBot = $fresh->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->node_id)->toBe(ChatTurnProcessor::SENTINEL_FINAL_CHECK);
});

test('confirm_exit from final_talk parent finalizes when student accepts', function () {
    $script = QuestionScript::factory()->create();
    $this->response->update([
        'pending_confirm_exit_node' => ChatTurnProcessor::SENTINEL_FINAL_TALK,
    ]);
    $this->fake->nextContinuationResult = 'exit';

    $msg = studentTurnAt($this->response, ChatTurnProcessor::SENTINEL_CONFIRM_EXIT, 'pode encerrar');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $msg);

    expect($this->response->fresh()->submitted_at)->not->toBeNull();
});

test('confirm_exit classifier failure defaults to exit', function () {
    $script = QuestionScript::factory()->withFreeTalk()->create();
    $this->response->update(['pending_confirm_exit_node' => 'ft1']);
    $this->fake->shouldThrow = true;

    $msg = studentTurnAt($this->response, ChatTurnProcessor::SENTINEL_CONFIRM_EXIT, 'resposta');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $msg);

    $fresh = $this->response->fresh();
    expect($fresh->pending_confirm_exit_node)->toBeNull();

    $lastBot = $fresh->chatMessages()->where('role', 'bot')->latest('id')->first();
    expect($lastBot->node_id)->toBe(ChatTurnProcessor::SENTINEL_FINAL_CHECK);
    expect($lastBot->classifier_status)->toBe('failed');
});

test('entering free_talk node resets engagement streak', function () {
    $script = QuestionScript::factory()->withFreeTalk()->create();
    $this->response->update(['low_engagement_streak' => 1]);

    $this->processor->openingTurn($script, $this->response);

    expect($this->response->fresh()->low_engagement_streak)->toBe(0);
});

test('recent_turns are sent to classifier in chronological order, excluding current answer', function () {
    $script = QuestionScript::factory()->withFreeTalk()->create();

    // Histórico (mais antigo → mais novo): "primeira", "segunda"
    ChatMessage::factory()->student()->create([
        'lesson_response_id' => $this->response->id,
        'node_id' => 'ft1',
        'content' => 'primeira',
    ]);
    ChatMessage::factory()->student()->create([
        'lesson_response_id' => $this->response->id,
        'node_id' => 'ft1',
        'content' => 'segunda',
    ]);

    $this->fake->nextEngagementDecision = EngagementDecision::DECISION_CONTINUE;
    $msg = studentTurnAt($this->response, 'ft1', 'atual');
    $this->processor->processStudentTurn($script, $this->response->fresh(), $msg);

    expect($this->fake->engagementInvocations)->toHaveCount(1);
    $invocation = $this->fake->engagementInvocations[0];
    expect($invocation['answer'])->toBe('atual');
    expect($invocation['recent_turns'])->toBe(['primeira', 'segunda']);
});
