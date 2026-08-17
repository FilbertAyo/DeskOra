<?php

namespace App\Services;

use App\Models\OfficialLetter;
use App\Models\OfficialLetterDownloadRequest;
use App\Models\User;
use App\Notifications\OfficialLetterDownloadDecisionNotification;
use App\Notifications\OfficialLetterDownloadRequestedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OfficialLetterDownloadAuthorizationService
{
    public function requestDownload(OfficialLetter $letter, User $requester, ?string $note = null): OfficialLetterDownloadRequest
    {
        $downloadRequest = DB::transaction(function () use ($letter, $requester, $note) {
            OfficialLetter::query()->whereKey($letter->id)->lockForUpdate()->firstOrFail();

            if ($this->hasAuthorizedRequest($letter)) {
                throw ValidationException::withMessages([
                    'download' => 'This letter is already authorized for download.',
                ]);
            }

            if ($this->hasPendingRequest($letter)) {
                throw ValidationException::withMessages([
                    'download' => 'A download authorization request is already pending.',
                ]);
            }

            return $letter->downloadRequests()->create([
                'requested_by' => $requester->id,
                'status' => OfficialLetterDownloadRequest::STATUS_PENDING,
                'request_note' => $note,
            ]);
        });

        $authorizers = $this->activeAuthorizers()
            ->reject(fn (User $user) => (string) $user->id === (string) $requester->id)
            ->values();

        if ($authorizers->isNotEmpty()) {
            Notification::send(
                $authorizers,
                new OfficialLetterDownloadRequestedNotification($letter->fresh(), $requester, $downloadRequest)
            );
        }

        return $downloadRequest;
    }

    public function authorize(OfficialLetter $letter, User $authorizer, ?string $decisionNote = null): OfficialLetterDownloadRequest
    {
        $downloadRequest = $this->decide(
            $letter,
            $authorizer,
            OfficialLetterDownloadRequest::STATUS_AUTHORIZED,
            $decisionNote,
        );

        $this->notifyRequester($letter, $downloadRequest->loadMissing('decider', 'requester'));

        return $downloadRequest;
    }

    public function reject(OfficialLetter $letter, User $authorizer, ?string $decisionNote = null): OfficialLetterDownloadRequest
    {
        $downloadRequest = $this->decide(
            $letter,
            $authorizer,
            OfficialLetterDownloadRequest::STATUS_REJECTED,
            $decisionNote,
        );

        $this->notifyRequester($letter, $downloadRequest->loadMissing('decider', 'requester'));

        return $downloadRequest;
    }

    /**
     * @return Collection<int, User>
     */
    public function activeAuthorizers(): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->permission(OfficialLetter::PERMISSION_AUTHORIZE_DOWNLOADS)
            ->get();
    }

    private function decide(
        OfficialLetter $letter,
        User $authorizer,
        string $status,
        ?string $decisionNote,
    ): OfficialLetterDownloadRequest {
        return DB::transaction(function () use ($letter, $authorizer, $status, $decisionNote) {
            OfficialLetter::query()->whereKey($letter->id)->lockForUpdate()->firstOrFail();

            $pending = OfficialLetterDownloadRequest::query()
                ->where('official_letter_id', $letter->id)
                ->where('status', OfficialLetterDownloadRequest::STATUS_PENDING)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $pending) {
                throw ValidationException::withMessages([
                    'download' => 'There is no pending download authorization request for this letter.',
                ]);
            }

            $pending->status = $status;
            $pending->decision_note = $decisionNote;
            $pending->decided_by = $authorizer->id;
            $pending->decided_at = now();
            $pending->save();

            return $pending;
        });
    }

    private function notifyRequester(OfficialLetter $letter, OfficialLetterDownloadRequest $downloadRequest): void
    {
        $requester = $downloadRequest->requester;
        if (! $requester) {
            return;
        }

        $requester->notify(new OfficialLetterDownloadDecisionNotification(
            $letter->fresh(),
            $downloadRequest,
        ));
    }

    private function hasAuthorizedRequest(OfficialLetter $letter): bool
    {
        return OfficialLetterDownloadRequest::query()
            ->where('official_letter_id', $letter->id)
            ->where('status', OfficialLetterDownloadRequest::STATUS_AUTHORIZED)
            ->exists();
    }

    private function hasPendingRequest(OfficialLetter $letter): bool
    {
        return OfficialLetterDownloadRequest::query()
            ->where('official_letter_id', $letter->id)
            ->where('status', OfficialLetterDownloadRequest::STATUS_PENDING)
            ->exists();
    }
}
