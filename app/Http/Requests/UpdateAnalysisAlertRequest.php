<?php

namespace App\Http\Requests;

use App\Models\DiaryAnalysisAlert;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida a triagem de um alerta de análise pelo professor.
 *
 * A posse da aula é verificada no controller (gate "view"), seguindo o mesmo
 * padrão da revisão de análises. Aqui restringimos a transição aos estados
 * conhecidos e exigimos uma nota ao descartar um alerta socioemocional — um
 * sinal sensível não deve ser silenciado sem justificativa do professor.
 */
class UpdateAnalysisAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(DiaryAnalysisAlert::STATUSES)],
            'teacher_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $alert = $this->route('alert');

            $dismissNoteRequired = $alert instanceof DiaryAnalysisAlert
                && in_array($alert->type, DiaryAnalysisAlert::TYPES_REQUIRING_DISMISS_NOTE, true)
                && $this->input('status') === DiaryAnalysisAlert::STATUS_DISMISSED;

            if ($dismissNoteRequired && trim((string) $this->input('teacher_note')) === '') {
                $validator->errors()->add(
                    'teacher_note',
                    'Descartar um alerta socioemocional exige uma nota do professor.',
                );
            }
        });
    }
}
