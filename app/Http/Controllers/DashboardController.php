<?php

namespace App\Http\Controllers;

use App\Models\AiProviderConfig;
use App\Models\AnalysisPrompt;
use App\Models\DiaryAnalysis;
use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\QuestionScript;
use App\Models\ResponseAlert;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();
        $role = $this->resolveRole($user);

        return inertia('dashboard', [
            'dashboardRole' => $role,
            'stats' => match ($role) {
                'teacher' => $this->teacherStats($user),
                'student' => $this->studentStats($user),
                'admin' => $this->adminStats(),
                default => null,
            },
        ]);
    }

    private function resolveRole(User $user): string
    {
        $selected = session('selected_role');
        if ($selected && $user->hasRole($selected)) {
            return $selected;
        }
        if ($user->isAdmin()) return 'admin';
        if ($user->isTeacher()) return 'teacher';
        if ($user->isStudent()) return 'student';
        return 'guest';
    }

    private function studentStats(User $user): array
    {
        $subjectIds = $user->subjectsAsStudent()->pluck('subjects.id');
        $now = Carbon::now();

        $lessons = Lesson::whereIn('subject_id', $subjectIds)
            ->with(['subject:id,name'])
            ->get();

        $respondedIds = LessonResponse::where('student_id', $user->id)
            ->whereNotNull('submitted_at')
            ->pluck('lesson_id')
            ->all();

        $pending = $lessons->filter(fn ($l) => $l->isAvailable() && ! in_array($l->id, $respondedIds));
        $upcoming = $lessons->filter(fn ($l) => $l->scheduled_at->gt($now));
        $answered = $lessons->filter(fn ($l) => in_array($l->id, $respondedIds));

        $nextLesson = $pending->sortBy('scheduled_at')->first()
            ?? $upcoming->sortBy('scheduled_at')->first();

        return [
            'pending_count' => $pending->count(),
            'answered_count' => $answered->count(),
            'upcoming_count' => $upcoming->count(),
            'total_count' => $lessons->count(),
            'completion_rate' => $lessons->count() > 0
                ? (int) round(($answered->count() / $lessons->count()) * 100)
                : 0,
            'next_lesson' => $nextLesson ? [
                'id' => $nextLesson->id,
                'title' => $nextLesson->title,
                'subject' => $nextLesson->subject->name,
                'scheduled_at' => $nextLesson->scheduled_at->toISOString(),
                'is_available' => $nextLesson->isAvailable(),
            ] : null,
            'subjects_count' => $subjectIds->count(),
        ];
    }

    private function teacherStats(User $user): array
    {
        $subjectIds = $user->subjectsAsTeacher()->pluck('id');

        $lessons = Lesson::whereIn('subject_id', $subjectIds)->get();
        $lessonIds = $lessons->pluck('id');

        $responseIds = LessonResponse::whereIn('lesson_id', $lessonIds)->pluck('id');

        $totalStudents = \DB::table('subject_student')
            ->whereIn('subject_id', $subjectIds)
            ->distinct('student_id')
            ->count('student_id');

        $totalResponses = LessonResponse::whereIn('lesson_id', $lessonIds)
            ->whereNotNull('submitted_at')
            ->count();

        $pendingReview = DiaryAnalysis::whereIn('lesson_response_id', $responseIds)
            ->where('status', DiaryAnalysis::STATUS_COMPLETED)
            ->count();

        $analysesLast7d = DiaryAnalysis::whereIn('lesson_response_id', $responseIds)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $availableLessons = $lessons->filter(fn ($l) => $l->isAvailable())->count();

        $unreadAlertRows = ResponseAlert::whereIn('lesson_response_id', $responseIds)
            ->whereNull('read_at')
            ->get(['severity']);
        $unreadAlerts = $unreadAlertRows->count();
        $highAlerts = $unreadAlertRows->where('severity', 'high')->count();

        $recentResponses = LessonResponse::whereIn('lesson_id', $lessonIds)
            ->whereNotNull('submitted_at')
            ->with(['student:id,name', 'lesson:id,title,subject_id', 'lesson.subject:id,name'])
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'student_name' => $r->student->name,
                'lesson_title' => $r->lesson->title,
                'subject_name' => $r->lesson->subject->name,
                'submitted_at' => $r->submitted_at?->toISOString(),
            ]);

        return [
            'total_students' => $totalStudents,
            'total_lessons' => $lessons->count(),
            'available_lessons' => $availableLessons,
            'total_responses' => $totalResponses,
            'pending_review' => $pendingReview,
            'analyses_last_7d' => $analysesLast7d,
            'subjects_count' => $subjectIds->count(),
            'recent_responses' => $recentResponses,
            'unread_alerts' => $unreadAlerts,
            'high_severity_alerts' => $highAlerts,
        ];
    }

    private function adminStats(): array
    {
        $activeProvider = AiProviderConfig::active();
        $activePrompt = AnalysisPrompt::with('latestVersion')->first();

        return [
            'active_provider' => $activeProvider ? [
                'provider' => $activeProvider->provider,
                'model' => $activeProvider->model,
            ] : null,
            'active_prompt' => $activePrompt ? [
                'name' => $activePrompt->name,
                'version' => $activePrompt->latestVersion?->version ?? 1,
            ] : null,
            'total_scripts' => QuestionScript::count(),
            'active_scripts' => QuestionScript::where('is_active', true)->count(),
            'total_users' => User::count(),
            'total_analyses' => DiaryAnalysis::count(),
            'analyses_last_7d' => DiaryAnalysis::where('created_at', '>=', Carbon::now()->subDays(7))->count(),
            'failed_analyses_last_7d' => DiaryAnalysis::where('status', DiaryAnalysis::STATUS_FAILED)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->count(),
        ];
    }
}
