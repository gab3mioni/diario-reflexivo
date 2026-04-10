<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Regras de validação reutilizáveis para perfis de utilizador.
 */
trait ProfileValidationRules
{
    /**
     * Retorna as regras de validação do perfil do utilizador.
     *
     * @param  int|null  $userId  ID do utilizador para ignorar na validação de unicidade.
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Retorna as regras de validação para o nome do utilizador.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Retorna as regras de validação para o email do utilizador.
     *
     * @param  int|null  $userId  ID do utilizador para ignorar na validação de unicidade.
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
