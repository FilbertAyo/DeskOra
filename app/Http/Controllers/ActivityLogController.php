<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Activity::class);

        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->latest();

        if ($request->filled('causer_id')) {
            $query->where('causer_id', (string) $request->input('causer_id'));
        }

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->input('log_name'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $activities = $query->paginate(40)->withQueryString();

        $staffUsers = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        $logNames = Activity::query()
            ->whereNotNull('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name')
            ->values();

        $events = Activity::query()
            ->whereNotNull('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event')
            ->values();

        $subjectTypes = Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->values();

        return view('settings.activity-log.index', compact(
            'activities',
            'staffUsers',
            'logNames',
            'events',
            'subjectTypes'
        ));
    }

    public function show(Request $request, Activity $activity): JsonResponse|View
    {
        $this->authorize('view', $activity);

        $activity->load(['causer', 'subject']);

        $payload = $this->activityDetailPayload($activity);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($payload);
        }

        return view('settings.activity-log.show', [
            'activity' => $activity,
            'detail' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function activityDetailPayload(Activity $activity): array
    {
        $causerLabel = 'System';
        if ($activity->causer?->name) {
            $causerLabel = $activity->causer->name;
        } elseif ($activity->causer_id) {
            $causerLabel = 'Unknown / deleted staff (#'.$activity->causer_id.')';
        }

        $subjectLabel = '—';
        if ($activity->subject_type) {
            $subjectLabel = class_basename($activity->subject_type).' #'.$activity->subject_id;
            if ($activity->subject && filled($activity->subject->title ?? null)) {
                $subjectLabel .= ' — '.$activity->subject->title;
            } elseif ($activity->subject && filled($activity->subject->name ?? null)) {
                $subjectLabel .= ' — '.$activity->subject->name;
            } elseif ($activity->subject && filled($activity->subject->request_no ?? null)) {
                $subjectLabel .= ' — '.$activity->subject->request_no;
            }
        }

        $properties = $activity->properties instanceof \Illuminate\Support\Collection
            ? $activity->properties->toArray()
            : (array) ($activity->properties ?? []);

        return [
            'id' => $activity->id,
            'when' => optional($activity->created_at)->format('Y-m-d H:i:s'),
            'staff' => $causerLabel,
            'staff_email' => $activity->causer?->email,
            'log_name' => $activity->log_name ?? 'default',
            'event' => $activity->event ?? '—',
            'description' => $activity->description,
            'subject' => $subjectLabel,
            'subject_type' => $activity->subject_type,
            'subject_id' => $activity->subject_id,
            'batch_uuid' => $activity->batch_uuid,
            'properties' => $properties,
            'properties_pretty' => $properties === []
                ? null
                : json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];
    }
}
