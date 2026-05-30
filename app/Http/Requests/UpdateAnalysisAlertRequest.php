<?php

namespace App\Http\Requests;

use App\Models\DiaryAnalysisAlert;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida a triagem de um alerta de análise pelo professor.
 *
 * A posse da aula é verificada no controller (gate "view"), seguindo o mesmo
 * padrão da revisão de análises. Aqui restringimos a transição aos estados
 * conhecidos do ciclo de vida do alerta.
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
        ];
    }
}
