<?php

namespace App\Services;

use App\Models\LessonResponse;
use App\Models\ResponseAlert;
use App\Notifications\ResponseAlertRaised;

class ResponseAlertService
{
    /**
     * Raise an alert for a lesson response. Severity escalation rules are
     * applied per type; the caller provides a baseline severity which may be
     * upgraded (never downgraded).
     */
    public function raise(
        LessonResponse $response,
        string $type,
        string $severity = ResponseAlert::SEVERITY_MEDIUM,
        ?string $reason = null,
    ): ResponseAlert {
        $severity = $this->resolveSeverity($response, $type, $severity);

        $alert = ResponseAlert::create([
            'lesson_response_id' => $response->id,
            'type' => $type,
            'severity' => $severity,
            'reason' => $reason !== null ? mb_substr($reason, 0, 500) : null,
        ]);

        $alert->loadMissing('lessonResponse.lesson.subject.teacher');
        $teacher = $alert->lessonResponse?->lesson?->subject?->teacher;
        if ($teacher) {
            $teacher->notify(new ResponseAlertRaised($alert));
        }

        return $alert;
    }

    /**
     * For absence alerts, check the student's recent responses and escalate
     * severity based on how many consecutive absences preceded this one.
     */
    private function resolveSeverity(LessonResponse $response, string $type, string $severity): string
    {
        if ($type !== ResponseAlert::TYPE_ABSENCE) {
            return $severity;
        }

        $previousAbsences = LessonResponse::query()
            ->where('student_id', $response->student_id)
            ->where('id', '!=', $response->id)
            ->whereHas('alerts', fn ($q) => $q->where('type', ResponseAlert::TYPE_ABSENCE))
            ->orderByDesc('created_at')
            ->limit(2)
            ->count();

        return match (true) {
            $previousAbsences >= 2 => ResponseAlert::SEVERITY_HIGH,
            $previousAbsences === 1 => ResponseAlert::SEVERITY_MEDIUM,
            default => ResponseAlert::SEVERITY_LOW,
        };
    }
}
