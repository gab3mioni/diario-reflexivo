<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAnalysisAlertRequest;
use App\Models\DiaryAnalysisAlert;
use App\Models\Lesson;
use App\Models\LessonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Triagem de alertas de análise pelo professor.
 *
 * Alertas são apenas sinais human-gated: esta ação muda o status (reconhecer
 * ou descartar), registra autor e momento e nada mais — nunca dispara automação.
 */
class DiaryAnalysisAlertController extends Controller
{
    /**
     * Atualiza o status de triagem de um alerta da resposta de diário.
     *
     * Reconhecer ou descartar grava o professor e o instante da triagem; voltar
     * para "pendente" limpa esses campos.
     */
    public function update(
        UpdateAnalysisAlertRequest $request,
        Lesson $lesson,
        LessonResponse $response,
        DiaryAnalysisAlert $alert,
    ): RedirectResponse {
        $this->assertAlertBelongsTo($alert, $response, $lesson);

        $status = (string) $request->validated('status');
        $triaged = $status !== DiaryAnalysisAlert::STATUS_PENDING;

        $alert->update([
            'status' => $status,
            'acknowledged_by' => $triaged ? $request->user()->id : null,
            'acknowledged_at' => $triaged ? now() : null,
        ]);

        return back();
    }

    /**
     * Garante que o alerta pertence à resposta e à aula da rota (anti-IDOR).
     *
     * O FormRequest já confirma que o professor é dono da aula; aqui fechamos o
     * elo para impedir agir sobre alerta de outra resposta via IDs cruzados.
     */
    private function assertAlertBelongsTo(DiaryAnalysisAlert $alert, LessonResponse $response, Lesson $lesson): void
    {
        $alert->loadMissing('analysis');

        abort_unless(
            $alert->analysis !== null
                && (int) $alert->analysis->lesson_response_id === (int) $response->id
                && (int) $response->lesson_id === (int) $lesson->id,
            404,
        );
    }
}
