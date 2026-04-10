<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Cria um novo utilizador a partir dos dados de registo.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Valida os dados de entrada e cria um novo utilizador registado.
     *
     * @param  array<string, string>  $input  Dados do formulário de registo.
     * @return \App\Models\User
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            // Atribui a role padrão de 'student' ao novo usuário
            $studentRole = Role::where('slug', 'student')->first();
            if ($studentRole) {
                $user->roles()->attach($studentRole->id);
            }

            return $user;
        });
    }
}
