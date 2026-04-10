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

    expect($result)->toBe('e-positive');
});

test('classifyContinuation returns exit by default on malformed response', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyContinuation('q?', 'resposta');

    expect($result)->toBe('exit');
});

test('classifyContinuation returns continue when AI says continue', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{"decision":"continue"}']]],
        ], 200),
    ]);

    $result = $this->classifier->classifyContinuation('q?', 'quero falar mais');

    expect($result)->toBe('continue');
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
