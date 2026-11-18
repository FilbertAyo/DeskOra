<?php

namespace App\Http\Controllers;

use App\Models\InHouseMeeting;
use App\Models\InHouseMeetingActionItem;
use App\Models\MeetingCallPeer;
use App\Models\MeetingCallSignal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MeetingCallController extends Controller
{
    private const PEER_TTL_SECONDS = 15;

    public function show(InHouseMeeting $meeting)
    {
        if ($meeting->isCancelled() || $meeting->isCompleted()) {
            return redirect()
                ->route('meetings.index')
                ->with('info', 'This voice call has ended.');
        }

        $this->authorize('joinCall', $meeting);

        /** @var User $user */
        $user = Auth::user();
        $meeting->load(['organizer:id,name', 'participants.user:id,name', 'actionItems.assignee:id,name']);

        // Drop this user's leftover signaling so rejoin starts clean.
        $this->clearUserCallState($meeting, $user, wipePeer: true);

        $canManageRecords = $user->can('manageRecords', $meeting);
        $canEndCall = $user->can('complete', $meeting);

        return Inertia::render('Meetings/Call', [
            'meeting' => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'agenda' => $meeting->agenda,
                'location' => $meeting->location,
                'status' => $meeting->status,
                'minutes_notes' => $meeting->minutes_notes,
                'is_instant' => $meeting->starts_at->lte(now()->addMinutes(5))
                    && $meeting->created_at?->gte(now()->subMinutes(15)),
                'when_label' => $meeting->starts_at->timezone(config('app.timezone'))->format('D, M j · g:i A'),
                'organizer_name' => $meeting->organizer?->name,
                'participants' => $meeting->participants->map(fn ($row) => [
                    'id' => (string) $row->user_id,
                    'name' => $row->user?->name,
                    'response' => $row->response,
                ])->values()->all(),
                'rsvp' => $meeting->rsvpSummary(),
                'action_items' => $meeting->actionItems->map(fn (InHouseMeetingActionItem $item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'assignee_id' => $item->assignee_id,
                    'assignee_name' => $item->assignee?->name,
                    'due_date' => $item->due_date?->format('Y-m-d'),
                    'status' => $item->status,
                ])->values()->all(),
            ],
            'me' => [
                'id' => (string) $user->id,
                'name' => $user->name,
            ],
            'canEditMinutes' => true,
            'canManageRecords' => $canManageRecords,
            'canEndCall' => $canEndCall,
            // Avoid loading every active staff user on join; assignees = people already on the meeting.
            'staffUsers' => $canManageRecords ? $this->assigneeOptions($meeting) : [],
            'urls' => [
                'index' => route('meetings.index'),
                'show' => route('meetings.show', $meeting),
                'saveMinutes' => route('meetings.call.minutes', $meeting),
                'saveActions' => route('meetings.call.actions', $meeting),
                'heartbeat' => route('meetings.call.heartbeat', $meeting),
                'peers' => route('meetings.call.peers', $meeting),
                'signal' => route('meetings.call.signal', $meeting),
                'signals' => route('meetings.call.signals', $meeting),
                'pushAudio' => route('meetings.call.audio.push', $meeting),
                'pullAudio' => route('meetings.call.audio.pull', $meeting),
                'leave' => route('meetings.call.leave', $meeting),
                'end' => route('meetings.call.end', $meeting),
            ],
            'iceServers' => $this->iceServers(),
        ]);
    }

    public function saveMinutes(Request $request, InHouseMeeting $meeting)
    {
        $this->authorize('joinCall', $meeting);

        $validated = $request->validate([
            'minutes_notes' => ['nullable', 'string', 'max:20000'],
        ]);

        $meeting->update([
            'minutes_notes' => $validated['minutes_notes'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'minutes_notes' => $meeting->minutes_notes,
        ]);
    }

    public function saveActions(Request $request, InHouseMeeting $meeting)
    {
        $this->authorize('manageRecords', $meeting);

        $validated = $request->validate([
            'action_items' => ['nullable', 'array', 'max:50'],
            'action_items.*.title' => ['required_with:action_items', 'string', 'max:255'],
            'action_items.*.assignee_id' => ['nullable', 'string', 'short_id', 'exists:users,id'],
            'action_items.*.due_date' => ['nullable', 'date'],
            'action_items.*.status' => ['nullable', Rule::in([
                InHouseMeetingActionItem::STATUS_OPEN,
                InHouseMeetingActionItem::STATUS_DONE,
            ])],
        ]);

        DB::transaction(function () use ($meeting, $validated) {
            $meeting->actionItems()->delete();

            foreach (array_values($validated['action_items'] ?? []) as $index => $item) {
                $meeting->actionItems()->create([
                    'title' => $item['title'],
                    'assignee_id' => $item['assignee_id'] ?? null,
                    'due_date' => $item['due_date'] ?? null,
                    'status' => $item['status'] ?? InHouseMeetingActionItem::STATUS_OPEN,
                    'sort_order' => $index,
                ]);
            }
        });

        $meeting->load('actionItems.assignee:id,name');

        return response()->json([
            'ok' => true,
            'action_items' => $meeting->actionItems->map(fn (InHouseMeetingActionItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'assignee_id' => $item->assignee_id,
                'assignee_name' => $item->assignee?->name,
                'due_date' => $item->due_date?->format('Y-m-d'),
                'status' => $item->status,
            ])->values()->all(),
        ]);
    }

    public function heartbeat(InHouseMeeting $meeting)
    {
        if ($response = $this->endedCallResponse($meeting)) {
            return $response;
        }

        $this->authorize('joinCall', $meeting);

        /** @var User $user */
        $user = Auth::user();

        MeetingCallPeer::query()->updateOrCreate(
            [
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ],
            [
                'last_seen_at' => now(),
            ]
        );

        $this->pruneStalePeers($meeting);
        $this->pruneOldSignals($meeting);

        return response()->json(['ok' => true]);
    }

    public function peers(InHouseMeeting $meeting)
    {
        if ($response = $this->endedCallResponse($meeting)) {
            return $response;
        }

        $this->authorize('joinCall', $meeting);

        $peers = MeetingCallPeer::query()
            ->with('user:id,name')
            ->where('meeting_id', $meeting->id)
            ->where('last_seen_at', '>=', now()->subSeconds(self::PEER_TTL_SECONDS))
            ->orderBy('user_id')
            ->get()
            ->map(fn (MeetingCallPeer $peer) => [
                'id' => (string) $peer->user_id,
                'name' => $peer->user?->name ?? 'Staff',
            ])
            ->values();

        return response()->json(['peers' => $peers, 'ended' => false]);
    }

    public function signal(Request $request, InHouseMeeting $meeting)
    {
        if ($response = $this->endedCallResponse($meeting)) {
            return $response;
        }

        $this->authorize('joinCall', $meeting);

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'to_user_id' => ['required', 'string', 'short_id', 'exists:users,id'],
            'type' => ['required', Rule::in([
                MeetingCallSignal::TYPE_OFFER,
                MeetingCallSignal::TYPE_ANSWER,
                MeetingCallSignal::TYPE_ICE,
            ])],
            'payload' => ['required', 'array'],
        ]);

        if ((string) $validated['to_user_id'] === (string) $user->id) {
            return response()->json(['message' => 'Cannot signal yourself.'], 422);
        }

        if (! $this->canReachPeer($meeting, $validated['to_user_id'])) {
            return response()->json(['message' => 'Peer is not in this call.'], 422);
        }

        MeetingCallSignal::create([
            'meeting_id' => $meeting->id,
            'from_user_id' => $user->id,
            'to_user_id' => $validated['to_user_id'],
            'type' => $validated['type'],
            'payload' => $validated['payload'],
        ]);

        return response()->json(['ok' => true]);
    }

    public function signals(InHouseMeeting $meeting)
    {
        if ($response = $this->endedCallResponse($meeting)) {
            return $response;
        }

        $this->authorize('joinCall', $meeting);

        /** @var User $user */
        $user = Auth::user();

        $rows = DB::transaction(function () use ($meeting, $user) {
            $pending = MeetingCallSignal::query()
                ->where('meeting_id', $meeting->id)
                ->where('to_user_id', $user->id)
                ->whereNull('consumed_at')
                ->orderBy('id')
                ->limit(30)
                ->lockForUpdate()
                ->get();

            if ($pending->isNotEmpty()) {
                MeetingCallSignal::query()
                    ->whereIn('id', $pending->pluck('id'))
                    ->update(['consumed_at' => now()]);
            }

            return $pending;
        });

        return response()->json([
            'signals' => $rows->map(fn (MeetingCallSignal $signal) => [
                'id' => $signal->id,
                'from_user_id' => (string) $signal->from_user_id,
                'type' => $signal->type,
                'payload' => $signal->payload,
            ])->values(),
        ]);
    }

    public function pushAudio(Request $request, InHouseMeeting $meeting)
    {
        if ($response = $this->endedCallResponse($meeting)) {
            return $response;
        }

        $this->authorize('joinCall', $meeting);

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'seq' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'audio' => ['nullable', 'string', 'max:30000'],
            'chunks' => ['nullable', 'array', 'max:8'],
            'chunks.*.seq' => ['required_with:chunks', 'integer', 'min:1', 'max:10000000'],
            'chunks.*.audio' => ['required_with:chunks', 'string', 'max:30000'],
        ]);

        $incoming = [];
        foreach ($validated['chunks'] ?? [] as $chunk) {
            $incoming[] = [
                'seq' => (int) $chunk['seq'],
                'audio' => (string) $chunk['audio'],
            ];
        }
        if (isset($validated['seq'], $validated['audio'])) {
            $incoming[] = [
                'seq' => (int) $validated['seq'],
                'audio' => (string) $validated['audio'],
            ];
        }

        if ($incoming === []) {
            return response()->json(['message' => 'No audio frames provided.'], 422);
        }

        if (Cache::add('meeting-call-audio-presence:'.$meeting->id.':'.$user->id, true, 3)) {
            MeetingCallPeer::query()->updateOrCreate(
                [
                    'meeting_id' => $meeting->id,
                    'user_id' => $user->id,
                ],
                [
                    'last_seen_at' => now(),
                ],
            );
        }

        $key = $this->audioCacheKey($meeting, (string) $user->id);
        $minSeq = min(array_column($incoming, 'seq'));
        $chunks = $minSeq === 1 ? [] : Cache::get($key, []);
        $nowMs = (int) round(microtime(true) * 1000);
        foreach ($incoming as $frame) {
            $chunks[] = [
                'seq' => $frame['seq'],
                'audio' => $frame['audio'],
                'at' => $nowMs,
            ];
        }
        $cutoff = $nowMs - 6000;
        $chunks = array_values(array_filter($chunks, fn (array $chunk) => (int) ($chunk['at'] ?? 0) >= $cutoff));
        $chunks = array_slice($chunks, -24);

        Cache::put($key, $chunks, now()->addSeconds(20));

        return response()->json(['ok' => true]);
    }

    public function pullAudio(Request $request, InHouseMeeting $meeting)
    {
        if ($response = $this->endedCallResponse($meeting)) {
            return $response;
        }

        $this->authorize('joinCall', $meeting);

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'after' => ['nullable', 'array'],
            'after.*' => ['integer', 'min:0'],
        ]);
        $after = $validated['after'] ?? [];

        $peerIds = MeetingCallPeer::query()
            ->where('meeting_id', $meeting->id)
            ->where('user_id', '!=', $user->id)
            ->pluck('user_id');

        $chunks = [];
        foreach ($peerIds as $peerId) {
            $cursor = (int) ($after[(string) $peerId] ?? 0);
            foreach (Cache::get($this->audioCacheKey($meeting, (string) $peerId), []) as $chunk) {
                $seq = (int) ($chunk['seq'] ?? 0);
                if ($seq <= $cursor || ! is_string($chunk['audio'] ?? null)) {
                    continue;
                }
                $chunks[] = [
                    'user_id' => (string) $peerId,
                    'seq' => $seq,
                    'audio' => $chunk['audio'],
                ];
            }
        }

        usort($chunks, fn (array $left, array $right) => $left['seq'] <=> $right['seq']);

        return response()->json(['chunks' => $chunks]);
    }

    public function leave(InHouseMeeting $meeting)
    {
        /** @var User $user */
        $user = Auth::user();

        // Allow leave even after the organizer ends the call.
        if ($user->can('view', $meeting)) {
            $this->clearUserCallState($meeting, $user, wipePeer: true);
        }

        return response()->json(['ok' => true]);
    }

    public function end(Request $request, InHouseMeeting $meeting)
    {
        $this->authorize('complete', $meeting);

        $this->forceEndCall($meeting);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'ended' => true]);
        }

        return redirect()
            ->route('meetings.index')
            ->with('success', 'Voice call ended.');
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function assigneeOptions(InHouseMeeting $meeting): array
    {
        $options = collect([
            [
                'id' => (string) $meeting->organizer_id,
                'name' => $meeting->organizer?->name ?? 'Organizer',
            ],
        ]);

        foreach ($meeting->participants as $participant) {
            if (! $participant->user_id) {
                continue;
            }

            $options->push([
                'id' => (string) $participant->user_id,
                'name' => $participant->user?->name ?? 'Staff',
            ]);
        }

        return $options
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function clearUserCallState(InHouseMeeting $meeting, User $user, bool $wipePeer = true): void
    {
        if ($wipePeer) {
            MeetingCallPeer::query()
                ->where('meeting_id', $meeting->id)
                ->where('user_id', $user->id)
                ->delete();
        }

        MeetingCallSignal::query()
            ->where('meeting_id', $meeting->id)
            ->where(function ($query) use ($user) {
                $query->where('from_user_id', $user->id)
                    ->orWhere('to_user_id', $user->id);
            })
            ->delete();

        Cache::forget($this->audioCacheKey($meeting, (string) $user->id));
    }

    /**
     * @return list<array{urls: string|list<string>, username?: string, credential?: string}>
     */
    private function iceServers(): array
    {
        $servers = [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun.cloudflare.com:3478'],
        ];

        $turnUrls = array_values(array_filter(array_map('trim', explode(',', (string) config('services.meeting_call.turn_urls')))));
        $username = (string) config('services.meeting_call.turn_username');
        $credential = (string) config('services.meeting_call.turn_credential');
        if ($turnUrls !== [] && $username !== '' && $credential !== '') {
            $servers[] = [
                'urls' => $turnUrls,
                'username' => $username,
                'credential' => $credential,
            ];
        }

        return $servers;
    }

    private function audioCacheKey(InHouseMeeting $meeting, string $userId): string
    {
        return 'meeting-call-audio:'.$meeting->id.':'.$userId;
    }

    private function endedCallResponse(InHouseMeeting $meeting): ?\Illuminate\Http\JsonResponse
    {
        if (! $meeting->isCancelled() && ! $meeting->isCompleted()) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'ended' => true,
            'peers' => [],
            'chunks' => [],
            'signals' => [],
        ]);
    }

    public function forceEndCall(InHouseMeeting $meeting): void
    {
        $meeting->update([
            'status' => InHouseMeeting::STATUS_COMPLETED,
            'ends_at' => now(),
        ]);

        $peerUserIds = MeetingCallPeer::query()
            ->where('meeting_id', $meeting->id)
            ->pluck('user_id');

        MeetingCallPeer::query()->where('meeting_id', $meeting->id)->delete();
        MeetingCallSignal::query()->where('meeting_id', $meeting->id)->delete();

        foreach ($peerUserIds as $peerUserId) {
            Cache::forget($this->audioCacheKey($meeting, (string) $peerUserId));
        }
    }

    private function pruneStalePeers(InHouseMeeting $meeting): void
    {
        MeetingCallPeer::query()
            ->where('meeting_id', $meeting->id)
            ->where('last_seen_at', '<', now()->subSeconds(self::PEER_TTL_SECONDS))
            ->delete();
    }

    private function pruneOldSignals(InHouseMeeting $meeting): void
    {
        MeetingCallSignal::query()
            ->where('meeting_id', $meeting->id)
            ->whereNotNull('consumed_at')
            ->where('consumed_at', '<', now()->subMinutes(5))
            ->limit(200)
            ->delete();
    }

    private function canReachPeer(InHouseMeeting $meeting, string $peerUserId): bool
    {
        if ((string) $meeting->organizer_id === $peerUserId) {
            return true;
        }

        // Prefer live call presence — avoids an extra participants query on every ICE packet.
        if (MeetingCallPeer::query()
            ->where('meeting_id', $meeting->id)
            ->where('user_id', $peerUserId)
            ->where('last_seen_at', '>=', now()->subSeconds(self::PEER_TTL_SECONDS))
            ->exists()) {
            return true;
        }

        return $meeting->participants()->where('user_id', $peerUserId)->exists();
    }
}
