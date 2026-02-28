<?php

namespace App\Http\Controllers;

use App\Services\TeacherStudentsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherStudentsController extends Controller
{
    public function __construct(
        private TeacherStudentsService $teacherStudentsService
    ) {
    }

    public function index(Request $request)
    {
        $teacher = Auth::user();

        if (!$teacher->isTeacher()) {
            abort(403, 'Acesso negado. Apenas professores podem acessar esta página.');
        }

        $students = $this->teacherStudentsService->getStudentsWithSubjects($teacher);

        return inertia('teacher/students/index', [
            'students' => $students,
        ]);
    }

    public function show(Request $request, $studentId)
    {
        $teacher = Auth::user();

        if (!$teacher->isTeacher()) {
            abort(403, 'Acesso negado.');
        }

        if (!$this->teacherStudentsService->teacherHasAccessToStudent($teacher, $studentId)) {
            abort(403, 'Você não tem permissão para visualizar este aluno.');
        }

        $student = $this->teacherStudentsService->getStudentForTeacher($teacher, $studentId);

        if (!$student) {
            abort(404, 'Aluno não encontrado.');
        }

        $lessons = $this->teacherStudentsService->getStudentLessonsForTeacher($teacher, $studentId);

        return inertia('teacher/students/show', [
            'student' => $student,
            'lessons' => $lessons,
        ]);
    }

    public function edit(Request $request, $studentId)
    {
        $teacher = Auth::user();

        if (!$teacher->isTeacher()) {
            abort(403, 'Acesso negado.');
        }

        if (!$this->teacherStudentsService->teacherHasAccessToStudent($teacher, $studentId)) {
            abort(403, 'Você não tem permissão para editar este aluno.');
        }

        $student = $this->teacherStudentsService->getStudentForTeacher($teacher, $studentId);

        if (!$student) {
            abort(404, 'Aluno não encontrado.');
        }

        $teacherSubjects = $teacher->subjectsAsTeacher()->get();

        return inertia('teacher/students/edit', [
            'student' => $student,
            'teacherSubjects' => $teacherSubjects,
        ]);
    }

    public function update(Request $request, $studentId)
    {
        $teacher = Auth::user();

        if (!$teacher->isTeacher()) {
            abort(403, 'Acesso negado.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $studentId,
            'subjects' => 'array',
            'subjects.*' => 'exists:subjects,id',
        ]);

        $student = \App\Models\User::findOrFail($studentId);

        if (!$this->teacherStudentsService->teacherHasAccessToStudent($teacher, $studentId)) {
            abort(403, 'Você não tem permissão para editar este aluno.');
        }

        $student->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (isset($validated['subjects'])) {
            $this->teacherStudentsService->syncStudentSubjects($teacher, $student, $validated['subjects']);
        }

        return redirect()->route('teacher.students.show', $studentId)
            ->with('success', 'Aluno atualizado com sucesso!');
    }
}