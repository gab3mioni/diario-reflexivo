<?php

namespace App\Http\Requests;

use App\Models\DiaryAnalysisAlert;
use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação da triagem de um alerta de análise pelo professor.
 *
 * Alertas são human-gated: apenas o professor dono da aula muda o status, e a
 * transição se restringe aos estados conhecidos do ciclo de vida do alerta.
 */
class UpdateAnalysisAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ?Lesson $lesson */
        $lesson = $this->route('lesson');

        return $lesson !== null && $this->user()?->can('view', $lesson);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(DiaryAnalysisAlert::STATUSES)],
        ];
    }
}
