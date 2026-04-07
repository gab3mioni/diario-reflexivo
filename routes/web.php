<?php

use App\Http\Controllers\AdminAiConfigController;
use App\Http\Controllers\AdminQuestionScriptController;
use App\Http\Controllers\LessonsController;
use App\Http\Controllers\RoleSelectionController;
use App\Http\Controllers\StudentLessonsController;
use App\Http\Controllers\TeacherLessonsController;
use App\Http\Controllers\TeacherStudentsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::post('/select-role', [RoleSelectionController::class, 'store'])->name('role.select');

    // Shared routes (dispatched by selected_role)
    Route::get('lessons', [LessonsController::class, 'index'])->name('lessons.index');
    Route::get('lessons/{lesson}', [LessonsController::class, 'show'])->name('lessons.show');

    // Teacher-only routes
    Route::middleware(['role:teacher'])->group(function () {
        Route::post('lessons', [TeacherLessonsController::class, 'store'])->name('lessons.store');
        Route::post('lessons/bulk', [TeacherLessonsController::class, 'storeBulk'])->name('lessons.store-bulk');
        Route::get('lessons/{lesson}/edit', [TeacherLessonsController::class, 'edit'])->name('lessons.edit');
        Route::put('lessons/{lesson}', [TeacherLessonsController::class, 'update'])->name('lessons.update');
        Route::delete('lessons/{lesson}', [TeacherLessonsController::class, 'destroy'])->name('lessons.destroy');

        Route::get('students', [TeacherStudentsController::class, 'index'])->name('students.index');
        Route::get('students/{student}', [TeacherStudentsController::class, 'show'])->name('students.show');
        Route::get('students/{student}/edit', [TeacherStudentsController::class, 'edit'])->name('students.edit');
        Route::put('students/{student}', [TeacherStudentsController::class, 'update'])->name('students.update');

        Route::get('diaries/{response}', [TeacherLessonsController::class, 'showAnalysis'])->name('diaries.show');
        Route::post('diaries/{response}/analyze', [TeacherLessonsController::class, 'requestAnalysis'])->name('diaries.analyze');
        Route::post('diaries/{response}/analyses/{analysis}/review', [TeacherLessonsController::class, 'reviewAnalysis'])->name('diaries.review');
    });

    // Student-only routes
    Route::middleware(['role:student'])->group(function () {
        Route::post('lessons/{lesson}/chat/start', [StudentLessonsController::class, 'startChat'])->name('lessons.chat.start');
        Route::post('lessons/{lesson}/chat/message', [StudentLessonsController::class, 'sendMessage'])->name('lessons.chat.message');
        Route::put('lessons/{lesson}/chat/draft', [StudentLessonsController::class, 'saveDraft'])->name('lessons.chat.draft');
    });

    // Admin-only routes
    Route::middleware(['role:admin'])->group(function () {
        Route::get('question-scripts', [AdminQuestionScriptController::class, 'index'])->name('question-scripts.index');
        Route::get('question-scripts/{questionScript}', [AdminQuestionScriptController::class, 'show'])->name('question-scripts.show');
        Route::put('question-scripts/{questionScript}', [AdminQuestionScriptController::class, 'update'])->name('question-scripts.update');
        Route::post('question-scripts/{questionScript}/toggle-active', [AdminQuestionScriptController::class, 'toggleActive'])->name('question-scripts.toggle-active');

        Route::get('ai-config', [AdminAiConfigController::class, 'index'])->name('ai-config.index');
        Route::put('ai-config/provider', [AdminAiConfigController::class, 'updateProvider'])->name('ai-config.update-provider');
        Route::put('ai-config/prompt', [AdminAiConfigController::class, 'updatePrompt'])->name('ai-config.update-prompt');
        Route::get('ai-config/prompt-history', [AdminAiConfigController::class, 'promptHistory'])->name('ai-config.prompt-history');
    });
});

require __DIR__ . '/settings.php';
