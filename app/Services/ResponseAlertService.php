<?php

namespace App\Services;

use App\Models\LessonResponse;
use App\Models\ResponseAlert;
use App\Notifications\ResponseAlertRaised;

/**
 * Serviço para criação e escalonamento de alertas sobre respostas de alunos.
 */
class ResponseAlertService
{
    /**
     * Cria um alerta para uma resposta de aula, aplicando regras de escalonamento de severidade.
     *
     * A severidade fornecida pelo chamador pode ser elevada (nunca rebaixada)
     * com base no histórico do aluno.
     *
     * @param  LessonResponse  $response  Resposta de aula associada.
     * @param  string          $type      Tipo do alerta (constantes TYPE_* de ResponseAlert).
     * @param  string          $severity  Severidade base (constantes SEVERITY_* de ResponseAlert).
     * @param  ?string         $reason    Motivo descritivo (truncado a 500 caracteres).
     * @return ResponseAlert
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
     * Resolve a severidade final para alertas de ausência com base no histórico recente.
     *
     * @param  LessonResponse  $response  Resposta de aula do aluno.
     * @param  string          $type      Tipo do alerta.
     * @param  string          $severity  Severidade base fornecida.
     * @return string  Severidade final após escalonamento.
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
