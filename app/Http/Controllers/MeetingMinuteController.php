<?php

namespace App\Http\Controllers;

use App\Models\MeetingMinute;
use App\Models\WorkPeriod;
use App\Services\MeetingMinuteNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MeetingMinuteController extends Controller
{
    public function __construct(
        private readonly MeetingMinuteNotificationService $meetingMinuteNotificationService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', MeetingMinute::class);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $canManageMinutes = $user->can('manage minutes');

        $query = WorkPeriod::query()
            ->with(['meetingMinute.uploader'])
            ->orderByDesc('year')
            ->orderByDesc('week_number');

        if ($request->filled('year')) {
            $query->where('year', $request->integer('year'));
        }

        $periods = $query->paginate(15)->withQueryString();
        $years = WorkPeriod::select('year')->distinct()->orderByDesc('year')->pluck('year');
        $currentPeriod = WorkPeriod::getCurrent();

        return view('meeting-minutes.index', compact(
            'periods',
            'years',
            'currentPeriod',
            'canManageMinutes',
        ));
    }

    public function create(Request $request)
    {
        $this->authorize('create', MeetingMinute::class);

        $request->validate([
            'period_id' => ['required', 'string', 'short_id', 'exists:work_periods,id'],
        ]);

        $period = WorkPeriod::findOrFail($request->period_id);

        if ($period->meetingMinute) {
            return redirect()
                ->route('meeting-minutes.edit', $period->meetingMinute)
                ->with('info', 'Minutes already exist for this work period. You can replace the file below.');
        }

        return view('meeting-minutes.create', compact('period'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', MeetingMinute::class);

        $validated = $request->validate([
            'period_id' => [
                'required',
                'string',
                'short_id',
                'exists:work_periods,id',
                Rule::unique('meeting_minutes', 'period_id'),
            ],
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $period = WorkPeriod::findOrFail($validated['period_id']);
        $file = $validated['file'];
        $path = $file->store('meeting-minutes/'.$period->id, 'public');

        $meetingMinute = MeetingMinute::create([
            'period_id' => $period->id,
            'stored_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        $this->meetingMinuteNotificationService->notifyMinutesUploaded(
            $meetingMinute,
            $request->user(),
        );

        return redirect()
            ->route('meeting-minutes.show', $meetingMinute)
            ->with('success', 'Meeting minutes uploaded successfully.');
    }

    public function show(MeetingMinute $meetingMinute)
    {
        $this->authorize('view', $meetingMinute);
        $meetingMinute->load(['period', 'uploader']);

        if (! $meetingMinute->hasFile()) {
            return redirect()
                ->route('meeting-minutes.index')
                ->with('error', 'The minutes file was not found in storage.');
        }

        return view('meeting-minutes.show', compact('meetingMinute'));
    }

    public function edit(MeetingMinute $meetingMinute)
    {
        $this->authorize('update', $meetingMinute);
        $meetingMinute->load(['period', 'uploader']);

        return view('meeting-minutes.edit', compact('meetingMinute'));
    }

    public function update(Request $request, MeetingMinute $meetingMinute)
    {
        $this->authorize('update', $meetingMinute);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $validated['file'];

        if ($meetingMinute->path && Storage::disk('public')->exists($meetingMinute->path)) {
            Storage::disk('public')->delete($meetingMinute->path);
        }

        $path = $file->store('meeting-minutes/'.$meetingMinute->period_id, 'public');

        $meetingMinute->update([
            'stored_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return redirect()
            ->route('meeting-minutes.show', $meetingMinute)
            ->with('success', 'Meeting minutes updated successfully.');
    }

    public function view(MeetingMinute $meetingMinute)
    {
        $this->authorize('view', $meetingMinute);

        if (! Storage::disk('public')->exists($meetingMinute->path)) {
            return back()->with('error', 'Minutes file was not found in storage.');
        }

        return response()->file(
            Storage::disk('public')->path($meetingMinute->path),
            [
                'Content-Type' => $meetingMinute->mime_type ?: 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$meetingMinute->stored_name.'"',
            ]
        );
    }

    public function download(MeetingMinute $meetingMinute)
    {
        $this->authorize('view', $meetingMinute);

        if (! Storage::disk('public')->exists($meetingMinute->path)) {
            return back()->with('error', 'Minutes file was not found in storage.');
        }

        return response()->download(
            Storage::disk('public')->path($meetingMinute->path),
            $meetingMinute->stored_name
        );
    }
}
