<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador de aulas que delega para o controlador do perfil ativo (professor ou aluno).
 */
class LessonsController extends Controller
{
    /**
     * Lista as aulas delegando para o controlador do perfil ativo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $role = $this->resolveRole();

        if ($role === 'teacher') {
            return app(TeacherLessonsController::class)->index($request);
        }

        return app(StudentLessonsController::class)->index();
    }

    /**
     * Exibe uma aula específica delegando para o controlador do perfil ativo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int|string  $lessonId
     * @return \Inertia\Response
     */
    public function show(Request $request, $lessonId)
    {
        $role = $this->resolveRole();

        if ($role === 'teacher') {
            return app(TeacherLessonsController::class)->show($lessonId);
        }

        return app(StudentLessonsController::class)->show($lessonId);
    }

    /**
     * Resolve o perfil ativo do usuário autenticado.
     *
     * @return string
     */
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
