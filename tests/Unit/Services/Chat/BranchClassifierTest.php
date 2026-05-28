<?php

use App\Models\AiProviderConfig;
use App\Models\AnalysisPrompt;
use App\Models\AnalysisPromptVersion;
use App\Services\Chat\BranchClassifier;
use App\Services\Chat\BranchClassifierException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Seed config + prompt que o BranchClassifier exige.
    // Usa firstOrCreate para evitar UniqueConstraintViolation entre test runs.
    // Desativa qualquer config existente e cria uma nova via factory.
    AiProviderConfig::query()->update(['is_active' => false]);
    $config = AiProviderConfig::factory()->create(['is_active' => true]);

    $prompt = AnalysisPrompt::query()->firstOrCreate(
        ['slug' => 'branch-classifier'],
        ['name' => 'Branch Classifier'],
    );

    if (! $prompt->latestVersion) {
        AnalysisPromptVersion::factory()->create([
            'analysis_prompt_id' => $prompt->id,
            'content' => 'Você é um classificador.',
        ]);
    }

    $this->classifier = app(BranchClassifier::class);
});

test('classifyBranch returns edge_id from AI response', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{"edge_id":"e-positive"}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyBranch('pergunta', 'resposta feliz', [
        ['edge_id' => 'e-positive', 'description' => 'sentimento positivo'],
        ['edge_id' => 'e-negative', 'description' => 'sentimento negativo'],
    ]);

    expect($result->edgeId)->toBe('e-positive');
    expect($result->promptVersionId)->not->toBeNull();
});

test('classifyContinuation returns exit by default on malformed response', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyContinuation('q?', 'resposta');

    expect($result->decision)->toBe('exit');
});

test('classifyContinuation returns continue when AI says continue', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{"decision":"continue"}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyContinuation('q?', 'quero falar mais');

    expect($result->decision)->toBe('continue');
});

test('classifyBranch wraps HTTP failure in BranchClassifierException', function () {
    Http::fake([
        '*' => Http::response('server error', 500),
    ]);

    $this->classifier->classifyBranch('q', 'a', [['edge_id' => 'e', 'description' => 'd']]);
})->throws(BranchClassifierException::class);

test('classifyBranch wraps missing prompt in BranchClassifierException', function () {
    // Remove the prompt to simulate misconfiguration.
    AnalysisPrompt::where('slug', 'branch-classifier')->delete();

    $this->classifier->classifyBranch('q', 'a', [['edge_id' => 'e', 'description' => 'd']]);
})->throws(BranchClassifierException::class);

test('classifier respects active_version_id pin when admin fixed an older version', function () {
    $prompt = AnalysisPrompt::where('slug', 'branch-classifier')->first();

    // beforeEach já criou v1. Adicionamos v2 (mais recente) e fixamos v1.
    $pinned = $prompt->latestVersion;
    $latest = AnalysisPromptVersion::factory()->create([
        'analysis_prompt_id' => $prompt->id,
        'content' => 'conteudo da v mais recente',
    ]);
    $prompt->update(['active_version_id' => $pinned->id]);

    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{"decision":"continue"}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyContinuation('q?', 'resposta');

    expect($result->promptVersionId)->toBe($pinned->id);
    expect($result->promptVersionId)->not->toBe($latest->id);
});

test('classifyEngagement decodes a high engagement / continue response', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{"engagement_level":"high","decision":"continue","rationale":"resposta elaborada"}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyEngagement('q', 'resposta longa e elaborada com detalhes', []);

    expect($result->engagementLevel)->toBe('high');
    expect($result->decision)->toBe('continue');
    expect($result->rationale)->toBe('resposta elaborada');
    expect($result->promptVersionId)->not->toBeNull();
});

test('classifyEngagement decodes a low engagement / reengage response', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{"engagement_level":"low","decision":"reengage","rationale":"resposta curta"}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyEngagement('q', 'ok', []);

    expect($result->engagementLevel)->toBe('low');
    expect($result->decision)->toBe('reengage');
});

test('classifyEngagement decodes ask_to_end when model reports persistent low engagement', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{"engagement_level":"low","decision":"ask_to_end","rationale":"tres seguidas vazias"}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyEngagement('q', 'sla', ['nada', 'ok']);

    expect($result->decision)->toBe('ask_to_end');
});

test('classifyEngagement decodes exit on explicit refusal', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{"engagement_level":"low","decision":"exit","rationale":"recusa"}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyEngagement('q', 'nao quero falar', []);

    expect($result->decision)->toBe('exit');
});

test('classifyEngagement normalizes invalid decision back to continue', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{"engagement_level":"medium","decision":"explodir","rationale":"x"}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyEngagement('q', 'resposta', []);

    expect($result->decision)->toBe('continue');
});

test('classifyEngagement normalizes invalid level to null', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{"engagement_level":"meio","decision":"continue"}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyEngagement('q', 'resposta', []);

    expect($result->engagementLevel)->toBeNull();
});

test('classifyEngagement defaults to continue when payload is malformed', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyEngagement('q', 'resposta', []);

    expect($result->decision)->toBe('continue');
    expect($result->engagementLevel)->toBeNull();
    expect($result->rationale)->toBeNull();
});

test('classifyEngagement respects active_version_id pin', function () {
    $prompt = AnalysisPrompt::where('slug', 'branch-classifier')->first();
    $pinned = $prompt->latestVersion;
    AnalysisPromptVersion::factory()->create([
        'analysis_prompt_id' => $prompt->id,
        'content' => 'nova versao',
    ]);
    $prompt->update(['active_version_id' => $pinned->id]);

    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{"engagement_level":"medium","decision":"continue"}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyEngagement('q', 'resposta', []);

    expect($result->promptVersionId)->toBe($pinned->id);
});

test('classifier falls back to latestVersion when no pin is set', function () {
    $prompt = AnalysisPrompt::where('slug', 'branch-classifier')->first();
    $prompt->update(['active_version_id' => null]);

    $latest = AnalysisPromptVersion::factory()->create([
        'analysis_prompt_id' => $prompt->id,
        'content' => 'conteudo novissimo',
    ]);

    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{"decision":"continue"}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyContinuation('q?', 'resposta');

    expect($result->promptVersionId)->toBe($latest->id);
});
