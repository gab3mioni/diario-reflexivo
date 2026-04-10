<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request de validação para atualização do perfil do utilizador.
 */
class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Retorna as regras de validação para atualização do perfil.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->profileRules($this->user()->id);

        // Students cannot update their name
        if ($this->user()->isStudent() && session('selected_role') === 'student') {
            unset($rules['name']);
        }

        return $rules;
    }
}
