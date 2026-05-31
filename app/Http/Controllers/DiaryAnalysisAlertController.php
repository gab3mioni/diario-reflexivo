<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAnalysisAlertRequest;
use App\Models\DiaryAnalysisAlert;
use App\Models\LessonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Triagem de alertas de análise pelo professor.
 *
 * Alertas são apenas sinais human-gated: esta ação muda o status (reconhecer
 * ou descartar), registra o revisor e o momento e nada mais — nunca dispara
 * automação a partir do estado da IA.
 */
class DiaryAnalysisAlertController extends Controller
{
    /**
     * Atualiza o status de triagem de um alerta da resposta de diário.
     *
     * Reconhecer ou descartar grava o professor revisor e o instante; voltar
     * para "pendente" limpa esses campos.
     */
    public function update(
        UpdateAnalysisAlertRequest $request,
        LessonResponse $response,
        DiaryAnalysisAlert $alert,
    ): RedirectResponse {
        $this->authorize('view', $response->lesson);

        abort_unless((int) $alert->lesson_response_id === (int) $response->id, 404);

        $status = (string) $request->validated('status');
        $triaged = $status !== DiaryAnalysisAlert::STATUS_PENDING;

        $alert->update([
            'status' => $status,
            'teacher_note' => $request->validated('teacher_note'),
            'reviewed_by' => $triaged ? Auth::id() : null,
            'reviewed_at' => $triaged ? now() : null,
        ]);

        return back();
    }
}
