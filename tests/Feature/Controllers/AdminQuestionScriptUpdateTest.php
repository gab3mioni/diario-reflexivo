<?php

use App\Models\QuestionScript;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->script = QuestionScript::factory()->create();
    $this->actingAs($this->admin);
});

function validPayload(): array
{
    return [
        'name' => 'Novo nome',
        'description' => 'descrição',
        'nodes' => [
            ['id' => 'start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => ['message' => 'início']],
            ['id' => 'q1', 'type' => 'question', 'position' => ['x' => 100, 'y' => 0], 'data' => ['message' => 'pergunta 1', 'collection_type' => 'free_text']],
            ['id' => 'end', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => ['message' => 'fim']],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'start', 'target' => 'q1', 'is_default' => true],
            ['id' => 'e2', 'source' => 'q1', 'target' => 'end', 'is_default' => true],
        ],
    ];
}

test('update accepts a valid graph', function () {
    $this->put(route('question-scripts.update', $this->script->id), validPayload())
        ->assertRedirect();

    expect($this->script->fresh()->name)->toBe('Novo nome');
});

test('update rejects graph without start node', function () {
    $payload = validPayload();
    $payload['nodes'] = array_filter($payload['nodes'], fn ($n) => $n['type'] !== 'start');
    $payload['nodes'] = array_values($payload['nodes']);

    $this->put(route('question-scripts.update', $this->script->id), $payload)
        ->assertSessionHasErrors();
});

test('update rejects graph without end node', function () {
    $payload = validPayload();
    $payload['nodes'] = array_filter($payload['nodes'], fn ($n) => $n['type'] !== 'end');
    $payload['nodes'] = array_values($payload['nodes']);
    $payload['edges'] = [['id' => 'e1', 'source' => 'start', 'target' => 'q1', 'is_default' => true]];

    $this->put(route('question-scripts.update', $this->script->id), $payload)
        ->assertSessionHasErrors();
});

test('update rejects edge pointing to non-existent node', function () {
    $payload = validPayload();
    $payload['edges'][] = ['id' => 'bad', 'source' => 'q1', 'target' => 'ghost', 'is_default' => false];

    $this->put(route('question-scripts.update', $this->script->id), $payload)
        ->assertSessionHasErrors();
});

test('update rejects unreachable node (no path to end)', function () {
    $payload = validPayload();
    $payload['nodes'][] = [
        'id' => 'orphan',
        'type' => 'question',
        'position' => ['x' => 300, 'y' => 100],
        'data' => ['message' => 'órfão', 'collection_type' => 'free_text'],
    ];
    // Nenhuma edge para orphan → não alcança end.

    $this->put(route('question-scripts.update', $this->script->id), $payload)
        ->assertSessionHasErrors();
});

test('update forbidden for non-admin', function () {
    $teacher = User::factory()->teacher()->create();
    $this->actingAs($teacher);

    $this->put(route('question-scripts.update', $this->script->id), validPayload())
        ->assertForbidden();
});
