<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('lesson_responses has chat_state columns from Fase 2 migration', function () {
    expect(Schema::hasColumn('lesson_responses', 'chat_state'))->toBeTrue();
    expect(Schema::hasColumn('lesson_responses', 'chat_state_since'))->toBeTrue();
});

test('chat_state column defaults to idle', function () {
    $column = collect(Schema::getColumns('lesson_responses'))
        ->firstWhere('name', 'chat_state');

    expect($column)->not->toBeNull();
    expect($column['default'])->toContain('idle');
});

test('Fase 3 performance indexes exist on response_alerts', function () {
    $indexes = collect(Schema::getIndexes('response_alerts'))->pluck('name');
    expect($indexes)->toContain('response_alerts_response_unread_idx');
});

test('Fase 3 performance indexes exist on lesson_responses', function () {
    $indexes = collect(Schema::getIndexes('lesson_responses'))->pluck('name');
    expect($indexes)->toContain('lesson_responses_msg_count_idx');
    expect($indexes)->toContain('lesson_responses_chat_state_index');
});

test('Fase 3 performance indexes exist on diary_analyses', function () {
    $indexes = collect(Schema::getIndexes('diary_analyses'))->pluck('name');
    expect($indexes)->toContain('diary_analyses_response_created_idx');
});

test('analysis_prompts has active_version_id column for pinning', function () {
    expect(Schema::hasColumn('analysis_prompts', 'active_version_id'))->toBeTrue();
});

test('chat_messages has prompt_version_id column for per-message tracking', function () {
    expect(Schema::hasColumn('chat_messages', 'prompt_version_id'))->toBeTrue();
});

test('lesson_responses has engagement tracking columns', function () {
    expect(Schema::hasColumn('lesson_responses', 'low_engagement_streak'))->toBeTrue();
    expect(Schema::hasColumn('lesson_responses', 'pending_confirm_exit_node'))->toBeTrue();
});

test('low_engagement_streak defaults to 0', function () {
    $column = collect(Schema::getColumns('lesson_responses'))
        ->firstWhere('name', 'low_engagement_streak');

    expect($column)->not->toBeNull();
    expect((string) $column['default'])->toBe('0');
});
