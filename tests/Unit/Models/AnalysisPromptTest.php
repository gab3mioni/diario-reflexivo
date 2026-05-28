<?php

use App\Models\AnalysisPrompt;
use App\Models\AnalysisPromptVersion;

test('resolveActiveVersion falls back to latestVersion when active_version_id is null', function () {
    $prompt = AnalysisPrompt::factory()->create(['active_version_id' => null]);

    $v1 = AnalysisPromptVersion::factory()->create([
        'analysis_prompt_id' => $prompt->id,
        'version' => 1,
        'content' => 'conteudo v1',
    ]);

    $v2 = AnalysisPromptVersion::factory()->create([
        'analysis_prompt_id' => $prompt->id,
        'version' => 2,
        'content' => 'conteudo v2',
    ]);

    $resolved = $prompt->fresh()->resolveActiveVersion();

    expect($resolved)->not->toBeNull();
    expect($resolved->id)->toBe($v2->id);
    expect($resolved->version)->toBe(2);
    expect($v1->id)->not->toBe($v2->id);
});

test('resolveActiveVersion respects active_version_id pin even when older than latest', function () {
    $prompt = AnalysisPrompt::factory()->create();

    $v1 = AnalysisPromptVersion::factory()->create([
        'analysis_prompt_id' => $prompt->id,
        'version' => 1,
        'content' => 'conteudo v1',
    ]);

    AnalysisPromptVersion::factory()->create([
        'analysis_prompt_id' => $prompt->id,
        'version' => 2,
        'content' => 'conteudo v2',
    ]);

    $prompt->update(['active_version_id' => $v1->id]);

    $resolved = $prompt->fresh()->resolveActiveVersion();

    expect($resolved->id)->toBe($v1->id);
    expect($resolved->version)->toBe(1);
});

test('resolveActiveVersion returns null when prompt has no versions', function () {
    $prompt = AnalysisPrompt::factory()->create(['active_version_id' => null]);

    expect($prompt->resolveActiveVersion())->toBeNull();
});
