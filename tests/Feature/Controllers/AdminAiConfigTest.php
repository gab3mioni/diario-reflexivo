<?php

use App\Models\AnalysisPrompt;
use App\Models\PromptVersionAudit;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    $this->prompt = AnalysisPrompt::where('slug', 'diary-analysis')->firstOrFail();
    $this->prompt->update(['active_version_id' => null]);

    $this->v1 = $this->prompt->versions()->where('version', 1)->firstOrFail();
    $this->v2 = $this->prompt->createVersion('v2 content');
});

test('setActiveVersion pins a valid version of the prompt', function () {
    $response = $this->put(route('ai-config.set-active-version'), [
        'slug' => 'diary-analysis',
        'version_id' => $this->v1->id,
    ]);

    $response->assertRedirect();
    expect($this->prompt->fresh()->active_version_id)->toBe($this->v1->id);
});

test('setActiveVersion clears the pin when version_id is null', function () {
    $this->prompt->update(['active_version_id' => $this->v1->id]);

    $response = $this->put(route('ai-config.set-active-version'), [
        'slug' => 'diary-analysis',
        'version_id' => null,
    ]);

    $response->assertRedirect();
    expect($this->prompt->fresh()->active_version_id)->toBeNull();
});

test('setActiveVersion rejects a version_id from a different prompt', function () {
    $otherPrompt = AnalysisPrompt::where('slug', 'branch-classifier')->firstOrFail();
    $otherVersion = $otherPrompt->createVersion('other prompt version');

    $response = $this->put(route('ai-config.set-active-version'), [
        'slug' => 'diary-analysis',
        'version_id' => $otherVersion->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($this->prompt->fresh()->active_version_id)->toBeNull();
});

test('setActiveVersion rejects unknown slug', function () {
    $response = $this->put(route('ai-config.set-active-version'), [
        'slug' => 'unknown-slug',
        'version_id' => $this->v1->id,
    ]);

    $response->assertSessionHasErrors('slug');
});

test('setActiveVersion rejects non-existent version_id', function () {
    $response = $this->put(route('ai-config.set-active-version'), [
        'slug' => 'diary-analysis',
        'version_id' => 999999,
    ]);

    $response->assertSessionHasErrors('version_id');
});

test('setActiveVersion records an audit row when pinning a version', function () {
    $this->put(route('ai-config.set-active-version'), [
        'slug' => 'diary-analysis',
        'version_id' => $this->v1->id,
    ]);

    $audit = PromptVersionAudit::where('analysis_prompt_id', $this->prompt->id)->sole();

    expect($audit->previous_version_id)->toBeNull();
    expect($audit->new_version_id)->toBe($this->v1->id);
    expect($audit->actor_id)->toBe($this->admin->id);
});

test('setActiveVersion records previous and new version when switching pin', function () {
    $this->prompt->update(['active_version_id' => $this->v1->id]);

    $this->put(route('ai-config.set-active-version'), [
        'slug' => 'diary-analysis',
        'version_id' => $this->v2->id,
    ]);

    $audit = PromptVersionAudit::where('analysis_prompt_id', $this->prompt->id)->sole();

    expect($audit->previous_version_id)->toBe($this->v1->id);
    expect($audit->new_version_id)->toBe($this->v2->id);
});

test('setActiveVersion records unpin with null new version', function () {
    $this->prompt->update(['active_version_id' => $this->v1->id]);

    $this->put(route('ai-config.set-active-version'), [
        'slug' => 'diary-analysis',
        'version_id' => null,
    ]);

    $audit = PromptVersionAudit::where('analysis_prompt_id', $this->prompt->id)->sole();

    expect($audit->previous_version_id)->toBe($this->v1->id);
    expect($audit->new_version_id)->toBeNull();
});

test('setActiveVersion does not record an audit when the pin is unchanged', function () {
    $this->prompt->update(['active_version_id' => $this->v1->id]);

    $this->put(route('ai-config.set-active-version'), [
        'slug' => 'diary-analysis',
        'version_id' => $this->v1->id,
    ]);

    expect(PromptVersionAudit::count())->toBe(0);
});

test('admin index page exposes both prompts with their resolved version', function () {
    $this->prompt->update(['active_version_id' => $this->v1->id]);

    $response = $this->get(route('ai-config.index'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('admin/ai-config/index')
            ->where('prompts.diary-analysis.is_pinned', true)
            ->where('prompts.diary-analysis.resolved_version.version', 1)
            ->where('prompts.diary-analysis.active_version_id', $this->v1->id)
    );
});
