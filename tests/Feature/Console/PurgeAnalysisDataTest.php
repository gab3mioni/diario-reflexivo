<?php

use App\Models\DiaryAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('purges raw_response older than the retention window and keeps recent ones', function () {
    $old = DiaryAnalysis::factory()->create([
        'raw_response' => 'lixo antigo',
        'created_at' => now()->subDays(120),
    ]);
    $recent = DiaryAnalysis::factory()->create([
        'raw_response' => 'ainda útil',
        'created_at' => now()->subDays(10),
    ]);

    $this->artisan('analyses:purge-raw', ['--days' => 90])->assertSuccessful();

    expect($old->fresh()->raw_response)->toBeNull()
        ->and($recent->fresh()->raw_response)->not->toBeNull();
});

test('purge-raw dry-run reports without changing data', function () {
    $old = DiaryAnalysis::factory()->create([
        'raw_response' => 'lixo antigo',
        'created_at' => now()->subDays(120),
    ]);

    $this->artisan('analyses:purge-raw', ['--days' => 90, '--dry-run' => true])->assertSuccessful();

    expect($old->fresh()->raw_response)->not->toBeNull();
});

test('purges teacher_notes older than the retention window', function () {
    $old = DiaryAnalysis::factory()->create([
        'teacher_notes' => 'nota antiga',
        'created_at' => now()->subDays(120),
    ]);
    $recent = DiaryAnalysis::factory()->create([
        'teacher_notes' => 'nota recente',
        'created_at' => now()->subDays(10),
    ]);

    $this->artisan('analyses:purge-notes', ['--days' => 90])->assertSuccessful();

    expect($old->fresh()->teacher_notes)->toBeNull()
        ->and($recent->fresh()->teacher_notes)->not->toBeNull();
});

test('purge-notes dry-run reports without changing data', function () {
    $old = DiaryAnalysis::factory()->create([
        'teacher_notes' => 'nota antiga',
        'created_at' => now()->subDays(120),
    ]);

    $this->artisan('analyses:purge-notes', ['--days' => 90, '--dry-run' => true])->assertSuccessful();

    expect($old->fresh()->teacher_notes)->not->toBeNull();
});
