@php
    $statusStyles = [
        'rejected' => ['class' => 'danger', 'icon' => 'ti ti-x', 'text' => 'Rejected'],
        'approved' => ['class' => 'success', 'icon' => 'ti ti-check', 'text' => 'Approved'],
        'paid' => ['class' => 'success', 'icon' => 'ti ti-cash', 'text' => 'Paid'],
        'resubmission' => ['class' => 'warning', 'icon' => 'ti ti-corner-up-left', 'text' => 'Resubmission'],
        'resubmitted' => ['class' => 'secondary', 'icon' => 'ti ti-refresh', 'text' => 'Resubmitted'],
        'created' => ['class' => 'info', 'icon' => 'ti ti-plus', 'text' => 'Created'],
    ];
@endphp

<div class="approval-feed">
    <div class="approval-item approval-item-created">
        <div class="approval-dot bg-primary">
            <i class="ti ti-clipboard text-white"></i>
        </div>
        <div class="approval-content">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <h6 class="mb-1">Request Created</h6>
                <span class="badge bg-light-primary border border-primary text-primary">Created</span>
            </div>
            <p class="mb-1 text-muted small">{{ $request->request_for }}</p>
            <small class="text-muted">
                <i class="ti ti-clock me-1"></i>{{ $request->created_at->format('d M, Y h:i A') }}
            </small>
        </div>
    </div>

    @forelse ($approval_logs as $approval)
        @php
            $action = strtolower($approval->action);
            $style = $statusStyles[$action] ?? ['class' => 'secondary', 'icon' => 'ti ti-info-circle', 'text' => ucfirst($approval->action)];
        @endphp
        <div class="approval-item">
            <div class="approval-dot bg-{{ $style['class'] }}">
                <i class="{{ $style['icon'] }} text-white"></i>
            </div>
            <div class="approval-content">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <h6 class="mb-1">{{ $style['text'] }}</h6>
                    <span class="badge bg-light-{{ $style['class'] }} border border-{{ $style['class'] }} text-{{ $style['class'] }}">{{ $style['text'] }}</span>
                </div>
                <p class="mb-1 small text-muted">
                    <i class="ti ti-user me-1"></i>{{ $approval->user->name ?? 'System' }}
                </p>
                <small class="text-muted d-block mb-2">
                    <i class="ti ti-clock me-1"></i>{{ optional($approval->acted_at ?? $approval->created_at)->format('d M, Y h:i A') }}
                </small>
                @if (!empty($approval->comment))
                    <div class="alert alert-light border mb-0 py-2 px-3">
                        <small class="text-muted">{{ $approval->comment }}</small>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="text-muted small">No approval events yet.</div>
    @endforelse
</div>

<style>
    .approval-feed {
        display: flex;
        flex-direction: column;
        gap: 0.9rem;
    }
    .approval-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        position: relative;
    }
    .approval-item:not(:last-child)::after {
        content: "";
        position: absolute;
        left: 17px;
        top: 38px;
        width: 2px;
        height: calc(100% + 0.9rem);
        background: #e9ecef;
    }
    .approval-dot {
        width: 34px;
        height: 34px;
        min-width: 34px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 0 4px #fff;
        z-index: 1;
    }
    .approval-content {
        flex: 1 1 auto;
        background: #fff;
        border: 1px solid #eef0f2;
        border-radius: 0.5rem;
        padding: 0.75rem;
    }
    .approval-content h6 {
        font-size: 0.95rem;
    }
</style>
