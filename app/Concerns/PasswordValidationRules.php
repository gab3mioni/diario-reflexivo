<?php

namespace App\Concerns;

use Illuminate\Validation\Rules\Password;

/**
 * Regras de validação reutilizáveis para palavras-passe.
 */
trait PasswordValidationRules
{
    /**
     * Retorna as regras de validação para a nova palavra-passe.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::default(), 'confirmed'];
    }

    /**
     * Retorna as regras de validação para a palavra-passe atual.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
