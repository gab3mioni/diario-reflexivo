<?php

namespace App\Http\Controllers;

use App\Services\Students\BulkLimitExceededException;
use App\Services\Students\BulkStudentParser;
use App\Services\TeacherStudentsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Controlador de gerenciamento de alunos pelo professor.
 */
class TeacherStudentsController extends Controller
{
    public function __construct(
        private TeacherStudentsService $teacherStudentsService
    ) {
    }

    /**
     * Lista todos os alunos vinculados às disciplinas do professor.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
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

    /**
     * Exibe os detalhes de um aluno e suas aulas nas disciplinas do professor.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int|string  $studentId
     * @return \Inertia\Response
     */
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

    /**
     * Exibe o formulário de edição de um aluno.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int|string  $studentId
     * @return \Inertia\Response
     */
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

    /**
     * Atualiza os dados de um aluno e sincroniza suas disciplinas.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int|string  $studentId
     * @return \Illuminate\Http\RedirectResponse
     */
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

        return redirect()->route('students.show', $studentId)
            ->with('success', 'Aluno atualizado com sucesso!');
    }

    /**
     * Exibe o formulário de criação de um novo aluno.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function create(Request $request)
    {
        $teacher = Auth::user();
        abort_unless($teacher->isTeacher(), 403);

        return inertia('teacher/students/create', [
            'subjects' => $teacher->subjectsAsTeacher()->get(['id', 'name']),
        ]);
    }

    /**
     * Cadastra um novo aluno e o vincula a uma disciplina do professor.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $teacher = Auth::user();
        abort_unless($teacher->isTeacher(), 403);

        $teacherSubjectIds = $teacher->subjectsAsTeacher()->pluck('id')->toArray();

        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'subject_id' => ['required', 'integer', Rule::in($teacherSubjectIds)],
        ]);

        $this->teacherStudentsService->createStudent($teacher, $validated);

        return redirect()->route('students.index')
            ->with('success', 'Aluno criado com sucesso.');
    }

    /**
     * Processa e retorna a pré-visualização do cadastro em lote de alunos.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Services\Students\BulkStudentParser  $parser
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkPreview(Request $request, BulkStudentParser $parser)
    {
        $teacher = Auth::user();
        abort_unless($teacher->isTeacher(), 403);

        $request->validate([
            'raw' => 'required|string|max:1048576',
        ]);

        try {
            $result = $parser->parse($teacher, $request->input('raw'));
        } catch (BulkLimitExceededException $e) {
            return back()->withErrors(['raw' => $e->getMessage()]);
        }

        return back()->with('preview', $result->toArray());
    }

    /**
     * Cadastra alunos em lote, ignorando e-mails já existentes.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkStore(Request $request)
    {
        $teacher = Auth::user();
        abort_unless($teacher->isTeacher(), 403);

        $teacherSubjectIds = $teacher->subjectsAsTeacher()->pluck('id')->toArray();

        $validated = $request->validate([
            'rows' => 'required|array|min:1|max:45',
            'rows.*.name' => 'required|string|min:2|max:255',
            'rows.*.email' => 'required|email|max:255|distinct',
            'rows.*.subject_id' => ['required', 'integer', Rule::in($teacherSubjectIds)],
        ]);

        // Filter out emails that already exist (ignore + feedback)
        $existing = \App\Models\User::whereIn(
            'email',
            array_column($validated['rows'], 'email')
        )->pluck('email')->flip();

        $toCreate = [];
        $skipped = [];
        foreach ($validated['rows'] as $row) {
            if ($existing->has($row['email'])) {
                $skipped[] = ['email' => $row['email'], 'reason' => 'já existe'];
                continue;
            }
            $toCreate[] = $row;
        }

        $result = $this->teacherStudentsService->createStudentsBulk($teacher, $toCreate);

        return redirect()->route('students.index')->with('bulk_result', [
            'created_count' => count($result['created']),
            'skipped_existing' => $skipped,
            'failed' => $result['failed'],
        ]);
    }
}