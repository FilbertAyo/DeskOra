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
        return $officialLetter->userHasFullAccess($user)
            || $officialLetter->userCanReviewDownloadRequest($user);
    }

    public function verify(User $user, OfficialLetter $officialLetter): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can(OfficialLetter::PERMISSION_MANAGE);
    }

    public function update(User $user, OfficialLetter $officialLetter): bool
    {
        return $officialLetter->userHasFullAccess($user);
    }

    public function share(User $user, OfficialLetter $officialLetter): bool
    {
        return $officialLetter->isOwnedBy($user);
    }

    public function delete(User $user, OfficialLetter $officialLetter): bool
    {
        return $officialLetter->isOwnedBy($user);
    }

    public function download(User $user, OfficialLetter $officialLetter): bool
    {
        if ($officialLetter->userCanAuthorizeDownloads($user)) {
            return $officialLetter->userHasFullAccess($user)
                || $officialLetter->hasDownloadRequest();
        }

        return $officialLetter->userHasFullAccess($user)
            && $officialLetter->isDownloadAuthorized();
    }

    public function requestDownload(User $user, OfficialLetter $officialLetter): bool
    {
        return $officialLetter->userHasFullAccess($user)
            && ! $officialLetter->isDownloadAuthorized()
            && ! $officialLetter->hasPendingDownloadRequest();
    }

    public function authorizeDownload(User $user, OfficialLetter $officialLetter): bool
    {
        return $officialLetter->userCanAuthorizeDownloads($user)
            && $officialLetter->hasPendingDownloadRequest();
    }

    public function rejectDownload(User $user, OfficialLetter $officialLetter): bool
    {
        return $this->authorizeDownload($user, $officialLetter);
    }
}
