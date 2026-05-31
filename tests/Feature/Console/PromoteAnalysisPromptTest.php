<?php

use App\Models\AnalysisPrompt;
use App\Models\AnalysisPromptVersion;
use App\Models\PromptVersionAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->prompt = AnalysisPrompt::factory()->create();
    $this->v1 = AnalysisPromptVersion::factory()->create([
        'analysis_prompt_id' => $this->prompt->id,
        'version' => 1,
    ]);
    $this->v2 = AnalysisPromptVersion::factory()->create([
        'analysis_prompt_id' => $this->prompt->id,
        'version' => 2,
    ]);
});

test('command promotes a version and records an audit row', function () {
    $this->artisan('prompt:promote', ['slug' => $this->prompt->slug, 'version' => 2])
        ->assertSuccessful();

    expect($this->prompt->fresh()->active_version_id)->toBe($this->v2->id);

    $this->assertDatabaseHas('prompt_version_audits', [
        'analysis_prompt_id' => $this->prompt->id,
        'previous_version_id' => null,
        'new_version_id' => $this->v2->id,
        'actor_id' => null,
    ]);
});

test('command records the previous version on a second promotion', function () {
    $this->artisan('prompt:promote', ['slug' => $this->prompt->slug, 'version' => 2])->assertSuccessful();
    $this->artisan('prompt:promote', ['slug' => $this->prompt->slug, 'version' => 1])->assertSuccessful();

    $this->assertDatabaseHas('prompt_version_audits', [
        'previous_version_id' => $this->v2->id,
        'new_version_id' => $this->v1->id,
    ]);
});

test('command is a no-op when the version is already active', function () {
    $this->prompt->update(['active_version_id' => $this->v2->id]);

    $this->artisan('prompt:promote', ['slug' => $this->prompt->slug, 'version' => 2])->assertSuccessful();

    expect(PromptVersionAudit::count())->toBe(0);
});

test('command fails for an unknown prompt slug', function () {
    $this->artisan('prompt:promote', ['slug' => 'nope-nao-existe', 'version' => 1])->assertFailed();
});

test('command fails for a version that does not exist', function () {
    $this->artisan('prompt:promote', ['slug' => $this->prompt->slug, 'version' => 99])->assertFailed();
});
