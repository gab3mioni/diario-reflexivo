<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Request de validação para seleção de role ativa pelo utilizador.
 */
class RoleSelectionRequest extends FormRequest
{
    /**
     * Verifica se o utilizador está autorizado a fazer esta requisição.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Retorna as regras de validação para a seleção de role.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:student,teacher,admin'],
        ];
    }
}