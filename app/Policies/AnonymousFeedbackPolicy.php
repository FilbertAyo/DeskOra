<?php

namespace App\Policies;

use App\Models\AnonymousFeedback;
use App\Models\User;

class AnonymousFeedbackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage anonymous feedback');
    }

    public function view(User $user, AnonymousFeedback $anonymousFeedback): bool
    {
        return $user->can('manage anonymous feedback');
    }

    public function update(User $user, AnonymousFeedback $anonymousFeedback): bool
    {
        return $user->can('manage anonymous feedback');
    }

    public function delete(User $user, AnonymousFeedback $anonymousFeedback): bool
    {
        return $user->can('manage anonymous feedback');
    }
}
