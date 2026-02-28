<?php

use App\Http\Controllers\RoleSelectionController;
use App\Http\Controllers\StudentLessonsController;
use App\Http\Controllers\TeacherLessonsController;
use App\Http\Controllers\TeacherStudentsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::post('/select-role', [RoleSelectionController::class, 'store'])->name('role.select');

    Route::middleware(['role:teacher'])->group(function () {
        Route::resource('teacher/students', TeacherStudentsController::class)
            ->only(['index', 'show', 'edit', 'update'])
            ->names('teacher.students');

        Route::resource('teacher/lessons', TeacherLessonsController::class)
            ->except(['create'])
            ->names('teacher.lessons');

        Route::post('teacher/lessons/bulk', [TeacherLessonsController::class, 'storeBulk'])
            ->name('teacher.lessons.store-bulk');
    });

    Route::middleware(['role:student'])->group(function () {
        Route::get('student/lessons', [StudentLessonsController::class, 'index'])
            ->name('student.lessons.index');

        Route::get('student/lessons/{lesson}', [StudentLessonsController::class, 'show'])
            ->name('student.lessons.show');

        Route::post('student/lessons/{lesson}/respond', [StudentLessonsController::class, 'store'])
            ->name('student.lessons.respond');
    });
});

require __DIR__ . '/settings.php';
