<x-app-layout>
    <div class="row align-items-center mb-4">
        <div class="col">
            <h2 class="h3 mb-0 page-title">
                <i class="ti ti-history me-2"></i>Activity detail
            </h2>
            <p class="text-muted mt-1 mb-0">Entry #{{ $activity->id }}</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('activity-log.index', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i>Back to list
            </a>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3 text-muted">When</dt>
                <dd class="col-sm-9">{{ $activity->created_at->format('Y-m-d H:i:s') }}</dd>

                <dt class="col-sm-3 text-muted">Staff</dt>
                <dd class="col-sm-9">{{ $activity->causer?->name ?? '—' }}
                    @if($activity->causer)
                        <span class="text-muted small">({{ $activity->causer->email }})</span>
                    @endif
                </dd>

                <dt class="col-sm-3 text-muted">Log name</dt>
                <dd class="col-sm-9">{{ $activity->log_name ?? 'default' }}</dd>

                <dt class="col-sm-3 text-muted">Event</dt>
                <dd class="col-sm-9"><code>{{ $activity->event ?? '—' }}</code></dd>

                <dt class="col-sm-3 text-muted">Description</dt>
                <dd class="col-sm-9">{{ $activity->description }}</dd>

                <dt class="col-sm-3 text-muted">Subject</dt>
                <dd class="col-sm-9 font-monospace small">
                    @if ($activity->subject_type)
                        {{ $activity->subject_type }} #{{ $activity->subject_id }}
                    @else
                        —
                    @endif
                </dd>

                @if ($activity->batch_uuid)
                    <dt class="col-sm-3 text-muted">Batch</dt>
                    <dd class="col-sm-9 font-monospace small">{{ $activity->batch_uuid }}</dd>
                @endif
            </dl>
        </div>
    </div>

    @if ($activity->properties instanceof \Illuminate\Support\Collection && $activity->properties->isNotEmpty())
        <div class="card shadow-none border mt-3">
            <div class="card-header">
                <h6 class="mb-0">Properties</h6>
            </div>
            <div class="card-body">
                <pre class="small bg-light p-3 rounded mb-0 overflow-auto" style="max-height: 320px;">{{ json_encode($activity->properties->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    @endif
</x-app-layout>
