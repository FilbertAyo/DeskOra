@php
    $appName = config('app.name', 'Adilisha Portal');
    $functions = [
        ['label' => 'Approvals', 'name' => 'One-click review'],
        ['label' => 'Plans', 'name' => 'Weekly planning'],
        ['label' => 'Cash', 'name' => 'Requisition'],
    ];
@endphp

<div class="auth-features-panel">
    <div class="auth-brand">
        <span class="auth-brand__mark">
            <img
                src="{{ asset('assets/images/logo-icon.png') }}"
                alt=""
            >
        </span>
        <span class="auth-brand__name">{{ $appName }}</span>
    </div>

    <div class="auth-features-panel__hero">
        <div class="auth-hero-kicker">
            <span class="auth-hero-kicker__bar"></span>
            <span class="auth-hero-kicker__text">Operations</span>
        </div>

        <h1 class="auth-features-panel__title">Every approval, one click away.</h1>
        <p class="auth-features-panel__subtitle">
            One intelligent workspace for planning, reporting,inventory ,management ,finance and approvals , so requests resolve before they pile up.
        </p>

        <div class="auth-overview-card">
            <div class="auth-overview-card__header">
                <span class="auth-overview-card__label">Workspace</span>
                <span class="auth-overview-card__meta">Functions</span>
            </div>

            <div class="auth-overview-stats">
                @foreach ($functions as $function)
                    <div class="auth-overview-stat">
                        <span class="auth-overview-stat__label">{{ $function['label'] }}</span>
                        <span class="auth-overview-stat__value">{{ $function['name'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="auth-overview-card__section">
                <span class="auth-overview-card__section-label">Key functions</span>
            </div>

            <div class="auth-overview-item">
                <span class="auth-overview-item__icon" aria-hidden="true">
                    <i class="ti ti-checks"></i>
                </span>
                <div class="flex-grow-1">
                    <p class="auth-overview-item__title mb-0">Approvals</p>
                    <p class="auth-overview-item__desc mb-0">Review and clear requests in one click</p>
                </div>
                <div class="auth-overview-actions" aria-hidden="true">
                    <span class="auth-overview-action auth-overview-action--ghost">
                        <i class="ti ti-x"></i>
                    </span>
                    <span class="auth-overview-action auth-overview-action--ok">
                        <i class="ti ti-check"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-features-panel__footer">
        <span class="auth-features-panel__footer-item">
            <i class="ti ti-shield-check"></i>
            Role-based access
        </span>
        <span class="auth-features-panel__footer-sep" aria-hidden="true"></span>
        <x-deskora-credit compact class="auth-deskora-credit" />
        <span class="auth-features-panel__footer-sep" aria-hidden="true"></span>
        <span>&copy; {{ now()->year }} {{ $appName }}</span>
    </div>
</div>
