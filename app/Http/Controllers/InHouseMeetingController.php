<?php

namespace App\Http\Controllers;

use App\Models\InHouseMeeting;
use App\Models\InHouseMeetingActionItem;
use App\Models\InHouseMeetingParticipant;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\InHouseMeetingNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InHouseMeetingController extends Controller
{
    public function __construct(
        private readonly InHouseMeetingNotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', InHouseMeeting::class);

        /** @var User $user */
        $user = Auth::user();
        $tab = $this->normalizeTab($request->string('tab')->toString());

        if ($tab === 'archive') {
            return $this->archiveIndex($request, $user);
        }

        $query = InHouseMeeting::query()
            ->with(['organizer:id,name', 'participants.user:id,name', 'period'])
            ->visibleTo($user);

        $pendingRsvpCount = InHouseMeetingParticipant::query()
            ->where('user_id', $user->id)
            ->where('response', InHouseMeetingParticipant::RESPONSE_PENDING)
            ->whereHas('meeting', fn ($meeting) => $meeting
                ->where('status', InHouseMeeting::STATUS_SCHEDULED)
                ->where('ends_at', '>=', now()))
            ->count();

        match ($tab) {
            'organized' => $query->where('organizer_id', $user->id)->orderByDesc('starts_at'),
            'awaiting' => $query
                ->where('status', InHouseMeeting::STATUS_SCHEDULED)
                ->where('ends_at', '>=', now())
                ->whereHas('participants', fn ($participants) => $participants
                    ->where('user_id', $user->id)
                    ->where('response', InHouseMeetingParticipant::RESPONSE_PENDING))
                ->orderBy('starts_at'),
            'past' => $query->past()->orderByDesc('starts_at'),
            default => $query
                ->where(function ($builder) use ($user) {
                    $builder
                        ->where('organizer_id', $user->id)
                        ->orWhereHas('participants', fn ($participants) => $participants->where('user_id', $user->id))
                        ->orWhere('meeting_type', InHouseMeeting::TYPE_WEEKLY);
                })
                ->upcoming()
                ->orderBy('starts_at'),
        };

        $meetings = $query->paginate(20)->withQueryString();

        return Inertia::render('Meetings/Index', [
            'tab' => $tab,
            'meetings' => [
                'data' => $meetings->getCollection()->map(fn (InHouseMeeting $meeting) => $this->listPayload($meeting, $user))->values(),
                'links' => $meetings->linkCollection(),
            ],
            'pendingRsvpCount' => $pendingRsvpCount,
            'canManageMinutes' => $user->can('manage minutes'),
            'staffUsers' => $this->staffOptions(),
            'workPeriods' => $this->workPeriodOptions(),
            'urls' => [
                'index' => route('meetings.index'),
                'store' => route('meetings.store'),
                'archive' => route('meetings.index', ['tab' => 'archive']),
                'minutesStore' => route('meeting-minutes.store'),
            ],
        ]);
    }

    public function create()
    {
        $this->authorize('create', InHouseMeeting::class);

        return redirect()->route('meetings.index', ['compose' => 'schedule']);
    }

    public function store(Request $request)
    {
        $this->authorize('create', InHouseMeeting::class);

        /** @var User $user */
        $user = Auth::user();
        $validated = $this->validatedMeeting($request);

        $isInstant = ($validated['timing'] ?? 'scheduled') === 'instant';

        $meeting = DB::transaction(function () use ($validated, $user, $isInstant) {
            $title = trim((string) ($validated['title'] ?? ''));
            if ($title === '') {
                $title = $isInstant
                    ? 'Voice call · '.$user->name
                    : 'Staff meeting';
            }

            $meeting = InHouseMeeting::create([
                'organizer_id' => $user->id,
                'title' => $title,
                'agenda' => $validated['agenda'] ?? null,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'location' => $validated['location'] ?? null,
                'join_url' => null,
                'meeting_type' => $validated['meeting_type'],
                'period_id' => $validated['period_id'] ?? null,
                'status' => InHouseMeeting::STATUS_SCHEDULED,
            ]);

            $participantIds = $this->resolveParticipantIds($validated, $user);
            $this->syncParticipants($meeting, $participantIds, resetResponses: true);

            return $meeting->load(['participants.user', 'organizer']);
        });

        $invitees = User::query()
            ->whereIn('id', $meeting->participants->pluck('user_id'))
            ->get();

        $this->notificationService->notifyInvited($meeting, $user, $invitees);

        if ($isInstant) {
            return redirect()
                ->route('meetings.call', $meeting)
                ->with('success', 'Voice call started. Waiting for participants…');
        }

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', 'Meeting scheduled successfully.');
    }

    public function show(InHouseMeeting $meeting)
    {
        $this->authorize('view', $meeting);

        /** @var User $user */
        $user = Auth::user();
        $meeting->load([
            'organizer:id,name,title',
            'participants.user:id,name,title',
            'actionItems.assignee:id,name',
            'period.meetingMinute.uploader',
        ]);

        $participant = $meeting->participantFor($user);

        return Inertia::render('Meetings/Show', [
            'meeting' => $this->detailPayload($meeting, $user),
            'myResponse' => $participant?->response,
            'canEdit' => $user->can('update', $meeting) && $meeting->isEditable(),
            'canCancel' => $user->can('cancel', $meeting),
            'canComplete' => $user->can('complete', $meeting),
            'canManageRecords' => $user->can('manageRecords', $meeting),
            'canEditMinutes' => $user->can('joinCall', $meeting),
            'canRsvp' => $user->can('rsvp', $meeting) && $meeting->isEditable(),
            'canManageMinutes' => $user->can('manage minutes'),
            'staffUsers' => $this->staffOptions(),
            'urls' => [
                'index' => route('meetings.index'),
                'edit' => route('meetings.edit', $meeting),
                'update' => route('meetings.update', $meeting),
                'cancel' => route('meetings.cancel', $meeting),
                'complete' => route('meetings.complete', $meeting),
                'rsvp' => route('meetings.rsvp', $meeting),
                'records' => route('meetings.records', $meeting),
                'call' => route('meetings.call', $meeting),
                'minutesStore' => route('meeting-minutes.store'),
            ],
        ]);
    }

    public function edit(InHouseMeeting $meeting)
    {
        $this->authorize('update', $meeting);

        if (! $meeting->isEditable()) {
            return redirect()
                ->route('meetings.show', $meeting)
                ->with('error', 'Only scheduled meetings can be edited.');
        }

        $meeting->load(['participants']);

        return Inertia::render('Meetings/Form', [
            'mode' => 'edit',
            'meeting' => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'agenda' => $meeting->agenda,
                'starts_at' => $meeting->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $meeting->ends_at->format('Y-m-d\TH:i'),
                'location' => $meeting->location,
                'join_url' => $meeting->join_url,
                'meeting_type' => $meeting->meeting_type,
                'period_id' => $meeting->period_id,
                'participant_user_ids' => $meeting->participants->pluck('user_id')->values()->all(),
                'invite_all_active' => false,
            ],
            'staffUsers' => $this->staffOptions(),
            'workPeriods' => $this->workPeriodOptions(),
            'submitUrl' => route('meetings.update', $meeting),
            'backUrl' => route('meetings.show', $meeting),
        ]);
    }

    public function update(Request $request, InHouseMeeting $meeting)
    {
        $this->authorize('update', $meeting);

        if (! $meeting->isEditable()) {
            return redirect()
                ->route('meetings.show', $meeting)
                ->with('error', 'Only scheduled meetings can be edited.');
        }

        /** @var User $user */
        $user = Auth::user();
        $validated = $this->validatedMeeting($request);
        $previousParticipantIds = $meeting->participants()->pluck('user_id')->map(fn ($id) => (string) $id)->all();

        DB::transaction(function () use ($meeting, $validated, $user) {
            $meeting->update([
                'title' => $validated['title'],
                'agenda' => $validated['agenda'] ?? null,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'location' => $validated['location'] ?? null,
                'meeting_type' => $validated['meeting_type'],
                'period_id' => $validated['period_id'] ?? null,
            ]);

            $participantIds = $this->resolveParticipantIds($validated, $user);
            $this->syncParticipants($meeting, $participantIds, resetResponses: false);
        });

        $meeting->refresh()->load(['participants.user', 'organizer']);
        $currentIds = $meeting->participants->pluck('user_id')->map(fn ($id) => (string) $id)->all();
        $newIds = array_values(array_diff($currentIds, $previousParticipantIds));
        $existingIds = array_values(array_intersect($currentIds, $previousParticipantIds));

        if ($newIds !== []) {
            $this->notificationService->notifyInvited(
                $meeting,
                $user,
                User::query()->whereIn('id', $newIds)->get(),
            );
        }

        if ($existingIds !== []) {
            $this->notificationService->notifyUpdated(
                $meeting,
                $user,
                User::query()->whereIn('id', $existingIds)->get(),
                'updated',
            );
        }

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', 'Meeting updated successfully.');
    }

    public function cancel(InHouseMeeting $meeting)
    {
        $this->authorize('cancel', $meeting);

        /** @var User $user */
        $user = Auth::user();

        $meeting->update(['status' => InHouseMeeting::STATUS_CANCELLED]);
        $meeting->load(['participants.user', 'organizer']);

        $this->notificationService->notifyCancelled(
            $meeting,
            $user,
            User::query()->whereIn('id', $meeting->participants->pluck('user_id'))->get(),
        );

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', 'Meeting cancelled.');
    }

    public function complete(InHouseMeeting $meeting)
    {
        $this->authorize('complete', $meeting);

        app(MeetingCallController::class)->forceEndCall($meeting);

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', 'Meeting marked as completed.');
    }

    public function rsvp(Request $request, InHouseMeeting $meeting)
    {
        $this->authorize('rsvp', $meeting);

        $validated = $request->validate([
            'response' => ['required', Rule::in([
                InHouseMeetingParticipant::RESPONSE_ACCEPTED,
                InHouseMeetingParticipant::RESPONSE_DECLINED,
            ])],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $participant = $meeting->participants()->where('user_id', $user->id)->firstOrFail();

        $participant->update([
            'response' => $validated['response'],
            'responded_at' => now(),
        ]);

        $meeting->load('organizer');
        $this->notificationService->notifyRsvp($meeting, $user, $validated['response']);

        $message = $validated['response'] === InHouseMeetingParticipant::RESPONSE_ACCEPTED
            ? 'You accepted the invitation.'
            : 'You declined the invitation.';

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', $message);
    }

    public function updateRecords(Request $request, InHouseMeeting $meeting)
    {
        $updatingActionPlans = $request->exists('action_items');
        $updatingMinutes = $request->exists('minutes_notes');

        if ($updatingActionPlans) {
            $this->authorize('manageRecords', $meeting);
        } else {
            $this->authorize('joinCall', $meeting);
        }

        $validated = $request->validate([
            'minutes_notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'action_items' => ['sometimes', 'nullable', 'array', 'max:50'],
            'action_items.*.title' => ['required_with:action_items', 'string', 'max:255'],
            'action_items.*.assignee_id' => ['nullable', 'string', 'short_id', 'exists:users,id'],
            'action_items.*.due_date' => ['nullable', 'date'],
            'action_items.*.status' => ['nullable', Rule::in([
                InHouseMeetingActionItem::STATUS_OPEN,
                InHouseMeetingActionItem::STATUS_DONE,
            ])],
        ]);

        DB::transaction(function () use ($meeting, $validated, $updatingMinutes, $updatingActionPlans) {
            if ($updatingMinutes) {
                $meeting->update([
                    'minutes_notes' => $validated['minutes_notes'] ?? null,
                ]);
            }

            if ($updatingActionPlans) {
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
            }

            if ($meeting->status === InHouseMeeting::STATUS_SCHEDULED && $meeting->ends_at->lt(now())) {
                $meeting->update(['status' => InHouseMeeting::STATUS_COMPLETED]);
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'minutes_notes' => $meeting->fresh()->minutes_notes,
            ]);
        }

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', 'Meeting records saved.');
    }

    private function archiveIndex(Request $request, User $user)
    {
        $query = WorkPeriod::query()
            ->with(['meetingMinute.uploader'])
            ->orderByDesc('year')
            ->orderByDesc('week_number');

        if ($request->filled('year')) {
            $query->where('year', $request->integer('year'));
        }

        $periods = $query->paginate(15)->withQueryString();
        $years = WorkPeriod::query()->select('year')->distinct()->orderByDesc('year')->pluck('year');
        $currentPeriod = WorkPeriod::getCurrent();

        $pendingRsvpCount = InHouseMeetingParticipant::query()
            ->where('user_id', $user->id)
            ->where('response', InHouseMeetingParticipant::RESPONSE_PENDING)
            ->whereHas('meeting', fn ($meeting) => $meeting
                ->where('status', InHouseMeeting::STATUS_SCHEDULED)
                ->where('ends_at', '>=', now()))
            ->count();

        return Inertia::render('Meetings/Index', [
            'tab' => 'archive',
            'meetings' => ['data' => [], 'links' => []],
            'archive' => [
                'data' => $periods->getCollection()->map(function (WorkPeriod $period) use ($currentPeriod) {
                    $minute = $period->meetingMinute;

                    return [
                        'id' => $period->id,
                        'weekNumber' => $period->week_number,
                        'year' => $period->year,
                        'dateRange' => $period->date_range,
                        'isCurrent' => $currentPeriod?->id === $period->id,
                        'minute' => $minute ? [
                            'showUrl' => route('meeting-minutes.show', $minute),
                            'downloadUrl' => route('meeting-minutes.download', $minute),
                            'editUrl' => route('meeting-minutes.edit', $minute),
                            'uploadedBy' => $minute->uploader?->name,
                            'updatedAt' => $minute->updated_at?->format('M d, Y H:i'),
                        ] : null,
                    ];
                })->values(),
                'links' => $periods->linkCollection(),
            ],
            'years' => $years->values(),
            'selectedYear' => $request->input('year'),
            'pendingRsvpCount' => $pendingRsvpCount,
            'canManageMinutes' => $user->can('manage minutes'),
            'staffUsers' => $this->staffOptions(),
            'workPeriods' => $this->workPeriodOptions(),
            'urls' => [
                'index' => route('meetings.index'),
                'store' => route('meetings.store'),
                'archive' => route('meetings.index', ['tab' => 'archive']),
                'minutesStore' => route('meeting-minutes.store'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedMeeting(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'agenda' => ['nullable', 'string', 'max:10000'],
            'timing' => ['required', Rule::in(['instant', 'scheduled'])],
            'duration_minutes' => ['nullable', 'integer', Rule::in([15, 30, 45, 60, 90, 120])],
            'starts_at' => ['nullable', 'required_if:timing,scheduled', 'date'],
            'ends_at' => ['nullable', 'required_if:timing,scheduled', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_type' => ['nullable', Rule::in([InHouseMeeting::TYPE_WEEKLY, InHouseMeeting::TYPE_ADHOC])],
            'period_id' => ['nullable', 'string', 'short_id', 'exists:work_periods,id'],
            'invite_all_active' => ['sometimes', 'boolean'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['string', 'short_id', 'exists:users,id'],
        ]);

        if (($validated['timing'] ?? null) === 'instant') {
            $duration = (int) ($validated['duration_minutes'] ?? 60);
            $validated['starts_at'] = now();
            $validated['ends_at'] = now()->addMinutes($duration);
            $validated['meeting_type'] = InHouseMeeting::TYPE_ADHOC;
            $validated['period_id'] = null;
        } else {
            $validated['meeting_type'] = $validated['meeting_type'] ?? InHouseMeeting::TYPE_ADHOC;
            if (blank($validated['title'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'A title is required for scheduled meetings.',
                ]);
            }
        }

        if (($validated['meeting_type'] ?? null) === InHouseMeeting::TYPE_ADHOC) {
            $validated['period_id'] = null;
        }

        if (! ($validated['invite_all_active'] ?? false)
            && ($validated['meeting_type'] ?? null) === InHouseMeeting::TYPE_ADHOC
            && empty($validated['participant_user_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'participant_user_ids' => 'Select at least one colleague to invite.',
            ]);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<string>
     */
    private function resolveParticipantIds(array $validated, User $organizer): array
    {
        if (! empty($validated['invite_all_active']) || ($validated['meeting_type'] ?? null) === InHouseMeeting::TYPE_WEEKLY) {
            return User::query()
                ->active()
                ->whereKeyNot($organizer->id)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();
        }

        return collect($validated['participant_user_ids'] ?? [])
            ->map(fn ($id) => (string) $id)
            ->reject(fn ($id) => $id === (string) $organizer->id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $userIds
     */
    private function syncParticipants(InHouseMeeting $meeting, array $userIds, bool $resetResponses): void
    {
        $existing = $meeting->participants()->get()->keyBy(fn (InHouseMeetingParticipant $row) => (string) $row->user_id);
        $keep = [];

        foreach ($userIds as $userId) {
            $keep[$userId] = true;

            if ($existing->has($userId)) {
                continue;
            }

            $meeting->participants()->create([
                'user_id' => $userId,
                'response' => InHouseMeetingParticipant::RESPONSE_PENDING,
                'responded_at' => null,
            ]);
        }

        foreach ($existing as $userId => $participant) {
            if (! isset($keep[$userId])) {
                $participant->delete();
            } elseif ($resetResponses) {
                $participant->update([
                    'response' => InHouseMeetingParticipant::RESPONSE_PENDING,
                    'responded_at' => null,
                ]);
            }
        }
    }

    /**
     * @return list<array{id: string, name: string, title: ?string}>
     */
    private function staffOptions(): array
    {
        return User::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'title'])
            ->map(fn (User $user) => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'title' => $user->title,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function workPeriodOptions(): array
    {
        return WorkPeriod::query()
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->limit(40)
            ->get()
            ->map(fn (WorkPeriod $period) => [
                'id' => (string) $period->id,
                'label' => 'Week '.$period->week_number.' · '.$period->year.' ('.$period->date_range.')',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(InHouseMeeting $meeting, User $user): array
    {
        $participant = $meeting->participantFor($user);
        $rsvp = $meeting->rsvpSummary();

        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'starts_at' => $meeting->starts_at->toIso8601String(),
            'ends_at' => $meeting->ends_at->toIso8601String(),
            'when_label' => $meeting->starts_at->timezone(config('app.timezone'))->format('D, M j · g:i A')
                .' – '.$meeting->ends_at->timezone(config('app.timezone'))->format('g:i A'),
            'day_key' => $meeting->starts_at->timezone(config('app.timezone'))->toDateString(),
            'day_label' => $meeting->starts_at->timezone(config('app.timezone'))->format('l, F j, Y'),
            'location' => $meeting->location,
            'join_url' => $meeting->join_url,
            'meeting_type' => $meeting->meeting_type,
            'status' => $meeting->status,
            'is_instant' => $meeting->starts_at->lte(now()->addMinutes(5))
                && $meeting->created_at?->gte(now()->subMinutes(10)),
            'is_live' => $meeting->status === InHouseMeeting::STATUS_SCHEDULED
                && $meeting->starts_at->lte(now())
                && $meeting->ends_at->gte(now()),
            'organizer' => $meeting->organizer?->name,
            'is_organizer' => $meeting->isOrganizer($user),
            'my_response' => $participant?->response,
            'rsvp' => $rsvp,
            'show_url' => route('meetings.show', $meeting),
            'call_url' => route('meetings.call', $meeting),
            'end_call_url' => $meeting->isOrganizer($user)
                && $meeting->status === InHouseMeeting::STATUS_SCHEDULED
                    ? route('meetings.call.end', $meeting)
                    : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(InHouseMeeting $meeting, User $user): array
    {
        $minute = $meeting->period?->meetingMinute;

        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'agenda' => $meeting->agenda,
            'starts_at' => $meeting->starts_at->toIso8601String(),
            'ends_at' => $meeting->ends_at->toIso8601String(),
            'when_label' => $meeting->starts_at->timezone(config('app.timezone'))->format('D, M j, Y · g:i A')
                .' – '.$meeting->ends_at->timezone(config('app.timezone'))->format('g:i A'),
            'location' => $meeting->location,
            'join_url' => $meeting->join_url,
            'meeting_type' => $meeting->meeting_type,
            'status' => $meeting->status,
            'minutes_notes' => $meeting->minutes_notes,
            'discussion_points' => $meeting->discussion_points,
            'decisions' => $meeting->decisions,
            'organizer' => [
                'id' => $meeting->organizer?->id,
                'name' => $meeting->organizer?->name,
                'title' => $meeting->organizer?->title,
            ],
            'is_organizer' => $meeting->isOrganizer($user),
            'period' => $meeting->period ? [
                'id' => $meeting->period->id,
                'weekNumber' => $meeting->period->week_number,
                'year' => $meeting->period->year,
                'dateRange' => $meeting->period->date_range,
            ] : null,
            'period_minute' => $minute ? [
                'showUrl' => route('meeting-minutes.show', $minute),
                'downloadUrl' => route('meeting-minutes.download', $minute),
                'editUrl' => route('meeting-minutes.edit', $minute),
                'uploadedBy' => $minute->uploader?->name,
            ] : null,
            'participants' => $meeting->participants->map(fn (InHouseMeetingParticipant $row) => [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'name' => $row->user?->name,
                'title' => $row->user?->title,
                'response' => $row->response,
                'responded_at' => $row->responded_at?->toIso8601String(),
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
        ];
    }

    private function normalizeTab(string $tab): string
    {
        return match ($tab) {
            'organized', 'awaiting', 'past', 'archive' => $tab,
            default => 'upcoming',
        };
    }
}
