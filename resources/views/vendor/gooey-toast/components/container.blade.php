@include('gooey-toast::partials.styles')

@once
@php
    $config = config('gooey-toast');
    $position = $config['position'] ?? 'bottom-center';
    $duration = $config['duration'] ?? 5000;
    $maxToasts = $config['max_toasts'] ?? 5;
    $theme = $config['theme'] ?? 'dark';

    $positionStyles = match($position) {
        'bottom-center' => 'position:fixed;bottom:2rem;left:50%;transform:translateX(-50%)',
        'bottom-right'  => 'position:fixed;bottom:2rem;right:2rem',
        'top-center'    => 'position:fixed;top:2rem;left:50%;transform:translateX(-50%)',
        'top-right'     => 'position:fixed;top:2rem;right:2rem',
        default         => 'position:fixed;bottom:2rem;left:50%;transform:translateX(-50%)',
    };

    // Load toasts from session (for PHP API)
    $sessionToasts = session('gooey-toasts', []);
    session()->forget('gooey-toasts');
@endphp

{{-- Dispatch session toasts after Alpine is ready (CDN Alpine is deferred). --}}
@if(!empty($sessionToasts))
<script>
    window.__gooeySessionToasts = {!! json_encode(array_values($sessionToasts)) !!};
    document.addEventListener('alpine:initialized', function () {
        (window.__gooeySessionToasts || []).forEach(function (toast) {
            window.dispatchEvent(new CustomEvent('toast', { detail: toast }));
        });
        window.__gooeySessionToasts = [];
    }, { once: true });
</script>
@endif

<div
    x-data="gooeyToast({{ $duration }}, {{ $maxToasts }}, '{{ $theme }}', '{{ $position }}')"
    @toast.window="add($event.detail)"
    @toast-update.window="updateToast($event.detail)"
    @toast-undo.window="addUndo($event.detail)"
    @toast-theme.window="theme = $event.detail.theme"
    @toast-register-icon.window="registerIcon($event.detail.name, $event.detail.svg)"
    @keydown.escape.window="dismissMostRecent()"
    role="region"
    aria-label="Notifications"
    style="{{ $positionStyles }};z-index:2000;display:flex;flex-direction:column;gap:12px;align-items:flex-end"
    :class="[theme === 'light' ? 'gooey-toast-light' : '', isStacked ? 'gooey-toast-stacked' : '']"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            class="gooey-toast-item"
            role="status"
            :aria-live="toast.type === 'error' ? 'assertive' : 'polite'"
            aria-atomic="true"
            :style="toastStyle(toast)"
            :class="[toast.expanded ? 'gooey-toast-expanded' : '', toast.collapsing ? 'gooey-toast-collapsing' : '', toast.duration > 0 && toast.progress === null ? 'gooey-toast-has-timer' : '', toast.progress !== null ? 'gooey-toast-has-progress' : '']"
            @animationend="if ($event.target === $el) toast.removing ? remove(toast.id) : (toast.entered = true)"
            @touchstart="swipeStart(toast, $event)"
            @touchmove="swipeMove(toast, $event)"
            @touchend="swipeEnd(toast, $event)"
        >
            <div class="gooey-toast-wrap">
                <div class="gooey-toast-outline"></div>
                {{-- Tab / Pill --}}
                <div class="gooey-toast-tab"
                     @click="toggle(toast)"
                     role="button"
                     tabindex="0"
                     :aria-expanded="toast.expanded"
                     @keydown.enter="toggle(toast)"
                     @keydown.space.prevent="toggle(toast)"
                >
                    {{-- Avatar (image) or Icon (SVG) --}}
                    <template x-if="toast.avatar">
                        <img class="gooey-toast-tab-avatar"
                             :src="toast.avatar"
                             :style="toast.avatarSize ? 'width:' + toast.avatarSize + ';height:' + toast.avatarSize : ''"
                             alt="Avatar"
                             @onerror="toast.avatar = null">
                    </template>
                    <template x-if="!toast.avatar">
                        <div class="gooey-toast-tab-icon"
                             :class="toast.color ? '' : 'gooey-toast-color-' + toast.type"
                             :style="toast.color ? 'color:' + toast.color : ''"
                             x-html="toast.iconSvg"></div>
                    </template>
                    <span class="gooey-toast-tab-text"
                          :class="toast.color ? '' : 'gooey-toast-color-' + toast.type"
                          :style="toast.color ? 'color:' + toast.color : ''"
                          x-text="toast.title"></span>

                    {{-- Undo button (shown inline for undo toasts) --}}
                    <template x-if="toast.isUndo">
                        <button class="gooey-toast-undo-btn" @click.stop="undoAction(toast)" aria-label="Undo action">
                            <span x-text="'Undo' + (toast.undoCountdown > 0 ? ' (' + toast.undoCountdown + ')' : '')"></span>
                        </button>
                    </template>

                    {{-- Chevron (hidden for undo toasts) --}}
                    <template x-if="!toast.isUndo">
                        <svg class="gooey-toast-tab-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </template>

                    <button class="gooey-toast-tab-close" @click.stop="dismiss(toast, true)" aria-label="Dismiss notification">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                    <div class="gooey-toast-border-tab"></div>
                </div>

                {{-- Body (expanded) --}}
                <div class="gooey-toast-body">
                    <template x-if="toast.message">
                        <div class="gooey-toast-message" x-text="toast.message"></div>
                    </template>
                    <template x-if="toast.message && toast.details.length > 0">
                        <div class="gooey-toast-sep"></div>
                    </template>
                    <template x-for="(row, idx) in toast.details" :key="idx">
                        <div class="gooey-toast-row">
                            <span class="gooey-toast-label" x-text="row.label"></span>
                            <span class="gooey-toast-value" x-text="row.value"></span>
                        </div>
                    </template>
                    <template x-if="toast.footer">
                        <div>
                            <div class="gooey-toast-sep"></div>
                            <div class="gooey-toast-footer" x-text="toast.footer"></div>
                        </div>
                    </template>
                    <template x-if="toast.actions && toast.actions.length > 0">
                        <div>
                            <div class="gooey-toast-sep"></div>
                            <div class="gooey-toast-actions">
                                <template x-for="(action, aidx) in toast.actions" :key="aidx">
                                    <button class="gooey-toast-action-btn"
                                            :class="action.confirming ? 'gooey-toast-action-confirm' : ''"
                                            :style="action.color ? 'background:' + action.color + '1a; color:' + action.color : ''"
                                            @click.stop="fireAction(toast, action)">
                                        <template x-if="action.iconSvg">
                                            <span class="gooey-toast-action-icon" x-html="action.iconSvg"></span>
                                        </template>
                                        <span x-text="action.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <div class="gooey-toast-border-body"></div>
                </div>

                {{-- Progress bar (shown when progress is set) --}}
                <template x-if="toast.progress !== null">
                    <div class="gooey-toast-progress">
                        <div class="gooey-toast-progress-track">
                            <div class="gooey-toast-progress-fill"
                                 :style="'width:' + (toast.progress * 100) + '%; background:' + toastColor(toast)"></div>
                        </div>
                        <div class="gooey-toast-progress-border"></div>
                    </div>
                </template>

                {{-- Timer bar block (shown when duration > 0 and no progress) --}}
                <template x-if="toast.duration > 0 && toast.progress === null">
                    <div class="gooey-toast-timer"
                         :class="'gooey-toast-timer-' + toast.type"
                         :style="toast.color ? '--timer-color:' + toast.color : ''">
                        <template x-for="i in toast.barCount" :key="i">
                            <div class="gooey-toast-timer-bar" :style="'--bar-delay: ' + (toast.duration * (toast.barCount - i + 1) / toast.barCount) + 'ms'"></div>
                        </template>
                        <div class="gooey-toast-timer-border"></div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    {{-- Stacking counter badge --}}
    <template x-if="hiddenCount > 0">
        <div class="gooey-toast-stack-badge" x-text="'+' + hiddenCount + ' more'"></div>
    </template>
</div>
@endonce

@include('gooey-toast::partials.scripts')
