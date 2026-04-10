<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cria um usuário com role de aluno e o autentica.
     */
    protected function actingAsStudent(): User
    {
        $user = User::factory()->student()->create();
        $this->actingAs($user);

        return $user;
    }

    /**
     * Cria um usuário com role de professor e o autentica.
     */
    protected function actingAsTeacher(): User
    {
        $user = User::factory()->teacher()->create();
        $this->actingAs($user);

        return $user;
    }

    /**
     * Cria um usuário com role de admin e o autentica.
     */
    protected function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }
}
