@php
    $featureGroups = [
        [
            'label' => 'Planning & Overview',
            'items' => [
                ['icon' => 'ti-dashboard', 'title' => 'Dashboard', 'desc' => 'Track org-wide activity at a glance'],
                ['icon' => 'ti-chart-line', 'title' => 'Year Plan', 'desc' => 'Annual goals and strategic planning'],
                ['icon' => 'ti-layout-kanban', 'title' => 'Matrix Panel', 'desc' => 'Cross-team progress matrix'],
            ],
        ],
        [
            'label' => 'Activities',
            'items' => [
                ['icon' => 'ti-calendar-event', 'title' => 'Weekly Plans', 'desc' => 'Plan work for each week'],
                ['icon' => 'ti-report-analytics', 'title' => 'Weekly Reports', 'desc' => 'Submit weekly activity reports'],
                ['icon' => 'ti-list-check', 'title' => 'Action Log', 'desc' => 'Follow up on assigned actions'],
                ['icon' => 'ti-folder', 'title' => 'Monthly Reports', 'desc' => 'Consolidated monthly summaries'],
                ['icon' => 'ti-stack', 'title' => 'All Documents', 'desc' => 'Browse every uploaded document'],
                ['icon' => 'ti-eye-check', 'title' => 'Review Activities', 'desc' => 'Approve submissions and budgets'],
            ],
        ],
        [
            'label' => 'Petty Cash',
            'items' => [
                ['icon' => 'ti-file', 'title' => 'Staff Requisitions', 'desc' => 'Request funds for staff needs'],
                ['icon' => 'ti-file-text', 'title' => 'Petty Cash Requests', 'desc' => 'Submit and track petty cash'],
                ['icon' => 'ti-users', 'title' => 'Approvals', 'desc' => 'Multi-level approval workflows'],
            ],
        ],
        [
            'label' => 'Finance & Admin',
            'items' => [
                ['icon' => 'ti-credit-card', 'title' => 'Payments & Cash Flow', 'desc' => 'Payments, deposits, and cash flow'],
                ['icon' => 'ti-file-text', 'title' => 'Org Reports', 'desc' => 'Organization reports and documents'],
                ['icon' => 'ti-calendar', 'title' => 'Work Periods', 'desc' => 'Manage reporting cycles'],
                ['icon' => 'ti-settings', 'title' => 'Settings', 'desc' => 'Users, roles, departments, and logs'],
            ],
        ],
    ];
@endphp

<div class="auth-features-panel">
    <div class="auth-features-panel__header">
        <img
            src="{{ asset('assets/images/logo.png') }}" style="height: 40px; width: auto;"
            alt="{{ config('app.name', 'Adilisha Portal') }}"
            class="auth-features-panel__logo"
        >
        <h1 class="auth-features-panel__title">{{ config('app.name', 'Adilisha Portal') }}</h1>
        <p class="auth-features-panel__subtitle">
            Your central hub for planning, reporting, documents, and petty cash — All in one place.
        </p>
    </div>

    <div class="auth-features-panel__body flex-grow-1">
        @foreach ($featureGroups as $group)
            <div class="auth-features-panel__group">
                <p class="auth-features-panel__group-label">{{ $group['label'] }}</p>
                <div class="row g-2">
                    @foreach ($group['items'] as $item)
                        <div class="col-sm-6">
                            <div class="auth-feature-card">
                                <span class="auth-feature-card__icon">
                                    <i class="ti {{ $item['icon'] }}"></i>
                                </span>
                                <div>
                                    <p class="auth-feature-card__title mb-0">{{ $item['title'] }}</p>
                                    <p class="auth-feature-card__desc mb-0">{{ $item['desc'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="auth-features-panel__footer">
        <x-deskora-credit compact />
    </div>
</div>
