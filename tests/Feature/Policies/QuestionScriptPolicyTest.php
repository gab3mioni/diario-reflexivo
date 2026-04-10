<?php

use App\Models\QuestionScript;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->script = QuestionScript::factory()->create();
});

test('admin can manage question scripts', function () {
    expect($this->admin->can('viewAny', QuestionScript::class))->toBeTrue();
    expect($this->admin->can('view', $this->script))->toBeTrue();
    expect($this->admin->can('update', $this->script))->toBeTrue();
    expect($this->admin->can('toggleActive', $this->script))->toBeTrue();
});

test('non-admin cannot manage question scripts', function () {
    expect($this->teacher->can('viewAny', QuestionScript::class))->toBeFalse();
    expect($this->teacher->can('update', $this->script))->toBeFalse();

    expect($this->student->can('viewAny', QuestionScript::class))->toBeFalse();
    expect($this->student->can('update', $this->script))->toBeFalse();
});
