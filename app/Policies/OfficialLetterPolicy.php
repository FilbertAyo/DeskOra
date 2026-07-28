<?php

namespace App\Policies;

use App\Models\OfficialLetter;
use App\Models\User;

class OfficialLetterPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OfficialLetter $officialLetter): bool
    {
        return $officialLetter->userHasFullAccess($user);
    }

    public function verify(User $user, OfficialLetter $officialLetter): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('manage official letters');
    }

    public function update(User $user, OfficialLetter $officialLetter): bool
    {
        return $officialLetter->userHasFullAccess($user);
    }

    public function share(User $user, OfficialLetter $officialLetter): bool
    {
        return $user->can('manage official letters') || $officialLetter->isOwnedBy($user);
    }

    public function delete(User $user, OfficialLetter $officialLetter): bool
    {
        return $user->can('manage official letters') || $officialLetter->isOwnedBy($user);
    }

    public function download(User $user, OfficialLetter $officialLetter): bool
    {
        return $officialLetter->userHasFullAccess($user);
    }
}
