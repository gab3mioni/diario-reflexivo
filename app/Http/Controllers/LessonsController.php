<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LessonsController extends Controller
{
    public function index(Request $request)
    {
        $role = $this->resolveRole();

        if ($role === 'teacher') {
            return app(TeacherLessonsController::class)->index($request);
        }

        return app(StudentLessonsController::class)->index();
    }

    public function show(Request $request, $lessonId)
    {
        $role = $this->resolveRole();

        if ($role === 'teacher') {
            return app(TeacherLessonsController::class)->show($lessonId);
        }

        return app(StudentLessonsController::class)->show($lessonId);
    }

    private function resolveRole(): string
    {
        $user = Auth::user();
        $selected = session('selected_role');

        if ($selected && $user->hasRole($selected)) {
            return $selected;
        }

        if ($user->isTeacher()) {
            return 'teacher';
        }

        if ($user->isStudent()) {
            return 'student';
        }

        abort(403);
    }
}
