<?php

namespace App\Policies;

use App\Models\QuestionScript;
use App\Models\User;

class QuestionScriptPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : false;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, QuestionScript $script): bool
    {
        return false;
    }

    public function update(User $user, QuestionScript $script): bool
    {
        return false;
    }

    public function toggleActive(User $user, QuestionScript $script): bool
    {
        return false;
    }
}
