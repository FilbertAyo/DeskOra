<x-app-layout>
    <link rel="stylesheet" href="{{ asset('assets/css/action-log-kanban.css') }}">

    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Action Log Board</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('action-logs.index') }}">Action Logs</a></li>
                                <li class="breadcrumb-item" aria-current="page">Board</li>
                            </ul>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('action-logs.index') }}" class="btn btn-sm btn-light">
                                <i class="ti ti-list me-1"></i> List view
                            </a>
                            @if($board['document'])
                                <a href="{{ route('action-logs.show', $board['document']) }}" class="btn btn-sm btn-outline-primary">
                                    Open log
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('action-logs.board') }}" class="card border shadow-none mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1">Work period</label>
                    <select name="period_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($workPeriods as $period)
                            <option value="{{ $period->id }}" @selected(($board['period']?->id ?? null) == $period->id)>
                                Week {{ $period->week_number }} of {{ $period->year }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Staff</label>
                    <select name="assignee_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All staff</option>
                        @foreach($board['assignees'] as $assignee)
                            <option value="{{ $assignee->id }}" @selected(($board['filters']['assignee_id'] ?? '') == $assignee->id)>
                                {{ $assignee->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Department</label>
                    <select name="department_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(($board['filters']['department_id'] ?? '') == $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('action-logs.board', ['period_id' => $board['period']?->id]) }}" class="btn btn-sm btn-outline-secondary">
                        Clear filters
                    </a>
                </div>
            </div>
        </div>
    </form>

    @if(! $board['document'])
        <div class="alert alert-light border">
            No action log found for this work period yet.
            @can('create action logs')
                <a href="{{ route('action-logs.create') }}">Open current week log</a>
            @endcan
        </div>
    @else
        <div class="kanban-board"
             data-document-id="{{ $board['document']->id }}"
             data-move-url="{{ route('action-logs.board-move', $board['document']) }}"
             data-csrf="{{ csrf_token() }}">
            @foreach($progressOptions as $status)
                @php $cards = $board['columns'][$status] ?? []; @endphp
                <section class="kanban-column" data-status="{{ $status }}">
                    <header class="kanban-column__header">
                        <span>{{ $status }}</span>
                        <span class="kanban-column__count">{{ count($cards) }}</span>
                    </header>
                    <div class="kanban-column__body" data-drop-zone="{{ $status }}">
                        @forelse($cards as $card)
                            <article
                                class="kanban-card{{ ($card['editable'] ?? false) ? '' : ' is-locked' }}"
                                draggable="{{ ($card['editable'] ?? false) ? 'true' : 'false' }}"
                                data-task-uid="{{ $card['task_uid'] }}"
                                data-progress="{{ $card['progress'] }}"
                            >
                                <div class="kanban-card__title">{{ $card['task'] }}</div>
                                @if(! empty($card['responsible_names']))
                                    <div class="kanban-card__meta">
                                        {{ implode(', ', $card['responsible_names']) }}
                                    </div>
                                @endif
                                @if(! empty($card['notes_progress']))
                                    <div class="kanban-card__notes">{{ \Illuminate\Support\Str::limit($card['notes_progress'], 90) }}</div>
                                @endif
                                @if(! empty($card['due_date']))
                                    <div class="kanban-card__due">Due {{ \Illuminate\Support\Str::of($card['due_date'])->limit(16) }}</div>
                                @endif
                            </article>
                        @empty
                            <div class="kanban-empty">No tasks</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
        @unless($board['can_edit'])
            <p class="text-muted small mt-2 mb-0">
                This period is closed or you can only view tasks. Drag-and-drop is disabled.
            </p>
        @endunless
    @endif

    @push('scripts')
        <script src="{{ asset('assets/js/action-log-kanban.js') }}"></script>
    @endpush
</x-app-layout>
