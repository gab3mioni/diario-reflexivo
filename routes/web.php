<?php

use App\Http\Controllers\RoleSelectionController;
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
    });
});

require __DIR__ . '/settings.php';
