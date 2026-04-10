<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Redefine a palavra-passe esquecida do utilizador.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Valida os dados e redefine a palavra-passe do utilizador.
     *
     * @param  \App\Models\User  $user  Utilizador cuja palavra-passe será redefinida.
     * @param  array<string, string>  $input  Dados do formulário de redefinição.
     * @return void
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}
