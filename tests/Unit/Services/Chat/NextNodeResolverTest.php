<?php

use App\Contracts\Chat\BranchClassifierContract;
use App\Models\QuestionScript;
use App\Services\Chat\NextNodeResolver;
use Tests\Support\FakeBranchClassifier;

beforeEach(function () {
    $this->fake = new FakeBranchClassifier();
    $this->app->instance(BranchClassifierContract::class, $this->fake);
    $this->resolver = app(NextNodeResolver::class);
});

test('returns skipped when current node does not exist', function () {
    $script = QuestionScript::factory()->create();
    $result = $this->resolver->resolve($script, 'non-existent', 'any');

    expect($result->nextNodeId)->toBeNull();
    expect($result->classifierStatus)->toBe('skipped');
});

test('returns skipped when node has no outgoing edges', function () {
    $script = QuestionScript::factory()->create([
        'nodes' => [['id' => 'dead', 'type' => 'question', 'data' => ['message' => 'q']]],
        'edges' => [],
    ]);

    $result = $this->resolver->resolve($script, 'dead', 'any');
    expect($result->nextNodeId)->toBeNull();
    expect($result->classifierStatus)->toBe('skipped');
});

test('skips classifier when there is exactly one outgoing edge', function () {
    $script = QuestionScript::factory()->create(); // start → q1 → end

    $result = $this->resolver->resolve($script, 'q1', 'whatever');

    expect($result->nextNodeId)->toBe('end');
    expect($result->classifierStatus)->toBe('skipped');
    expect($this->fake->branchCalls)->toBe(0);
});

test('option collection matches the edge with the literal label', function () {
    $script = QuestionScript::factory()->create([
        'nodes' => [
            ['id' => 'q', 'type' => 'question', 'data' => ['collection_type' => 'option', 'message' => 'q']],
            ['id' => 'a', 'type' => 'end', 'data' => []],
            ['id' => 'b', 'type' => 'end', 'data' => []],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'q', 'target' => 'a', 'condition' => ['description' => 'Sim']],
            ['id' => 'e2', 'source' => 'q', 'target' => 'b', 'condition' => ['description' => 'Não'], 'is_default' => true],
        ],
    ]);

    $result = $this->resolver->resolve($script, 'q', 'sim');

    expect($result->nextNodeId)->toBe('a');
    expect($result->classifierStatus)->toBe('ok');
});

test('option collection falls back to default edge when no label matches', function () {
    $script = QuestionScript::factory()->create([
        'nodes' => [
            ['id' => 'q', 'type' => 'question', 'data' => ['collection_type' => 'option', 'message' => 'q']],
            ['id' => 'a', 'type' => 'end', 'data' => []],
            ['id' => 'b', 'type' => 'end', 'data' => []],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'q', 'target' => 'a', 'condition' => ['description' => 'Sim']],
            ['id' => 'e2', 'source' => 'q', 'target' => 'b', 'condition' => ['description' => 'Não'], 'is_default' => true],
        ],
    ]);

    $result = $this->resolver->resolve($script, 'q', 'talvez');

    expect($result->nextNodeId)->toBe('b');
    expect($result->classifierStatus)->toBe('default_fallback');
});

test('free_text calls classifier and follows chosen edge', function () {
    $script = QuestionScript::factory()->withBranching()->create();

    $this->fake->nextBranchResult = 'e1';

    $result = $this->resolver->resolve($script, 'q1', 'tive um dia ótimo');

    expect($result->nextNodeId)->toBe('positive');
    expect($result->classifierStatus)->toBe('ok');
    expect($this->fake->branchCalls)->toBe(1);
});

test('free_text returns default_fallback when classifier returns empty', function () {
    $script = QuestionScript::factory()->withBranching()->create();
    $this->fake->nextBranchResult = '';

    $result = $this->resolver->resolve($script, 'q1', 'inconclusivo');

    expect($result->nextNodeId)->toBe('end'); // default edge
    expect($result->classifierStatus)->toBe('default_fallback');
});

test('free_text returns default_fallback when classifier returns unknown edge', function () {
    $script = QuestionScript::factory()->withBranching()->create();
    $this->fake->nextBranchResult = 'bogus-id';

    $result = $this->resolver->resolve($script, 'q1', 'hmm');

    expect($result->nextNodeId)->toBe('end');
    expect($result->classifierStatus)->toBe('default_fallback');
});

test('free_text catches classifier exception and uses default', function () {
    $script = QuestionScript::factory()->withBranching()->create();
    $this->fake->shouldThrow = true;

    $result = $this->resolver->resolve($script, 'q1', 'anything');

    expect($result->nextNodeId)->toBe('end');
    expect($result->classifierStatus)->toBe('failed');
    expect($result->classifierReason)->toContain('fake classifier');
});
