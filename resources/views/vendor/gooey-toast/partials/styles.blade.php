@once
{{-- Gooey SVG filter --}}
<svg style="position:absolute;width:0;height:0;pointer-events:none">
    <defs>
        <filter id="gooey-toast-filter">
            <feGaussianBlur in="SourceGraphic" stdDeviation="7" result="blur"/>
            <feColorMatrix in="blur" mode="matrix"
                values="1 0 0 0 0
                        0 1 0 0 0
                        0 0 1 0 0
                        0 0 0 40 -20" result="goo"/>
            <feComposite in="SourceGraphic" in2="goo" operator="atop"/>
        </filter>
    </defs>
</svg>

<style>
    :root {
        --gooey-toast-bg: #1c1c1e;
        --gooey-toast-text: #e5e5e5;
        --gooey-toast-text-muted: #666;
        --gooey-toast-text-subtle: #555;
        --gooey-toast-border: rgba(255,255,255,0.08);
        --gooey-toast-action-bg: rgba(255,255,255,0.13);
        --gooey-toast-action-bg-hover: rgba(255,255,255,0.19);
        --gooey-toast-action-text: #e5e5e5;
        --gooey-toast-action-text-hover: #fff;
        --gooey-toast-sep: rgba(255,255,255,0.07);
    }
    .gooey-toast-light {
        --gooey-toast-bg: #ffffff;
        --gooey-toast-text: #1c1c1e;
        --gooey-toast-text-muted: #888;
        --gooey-toast-text-subtle: #999;
        --gooey-toast-border: rgba(0,0,0,0.08);
        --gooey-toast-action-bg: rgba(0,0,0,0.06);
        --gooey-toast-action-bg-hover: rgba(0,0,0,0.10);
        --gooey-toast-action-text: #1c1c1e;
        --gooey-toast-action-text-hover: #000;
        --gooey-toast-sep: rgba(0,0,0,0.07);
    }

    .gooey-toast-wrap {
        position: relative;
        text-align: center;
    }
    .gooey-toast-expanded .gooey-toast-wrap,
    .gooey-toast-collapsing .gooey-toast-wrap,
    .gooey-toast-has-timer .gooey-toast-wrap,
    .gooey-toast-has-progress .gooey-toast-wrap {
        filter: url(#gooey-toast-filter);
    }

    .gooey-toast-tab {
        position: relative;
        z-index: 2;
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 11px 16px;
        background: var(--gooey-toast-bg);
        border-radius: 14px;
        cursor: pointer;
        margin: 0 auto;
        transition: transform 0.25s ease;
    }
    .gooey-toast-tab:active { transform: scale(0.97); }

    .gooey-toast-tab-icon { width: 18px; height: 18px; flex-shrink: 0; }
    .gooey-toast-tab-avatar { width: 18px; height: 18px; flex-shrink: 0; border-radius: 50%; object-fit: cover; }
    .gooey-toast-tab-text { font-size: 13.5px; font-weight: 500; line-height: 1; letter-spacing: -0.01em; }

    .gooey-toast-tab-chevron {
        width: 14px; height: 14px; color: var(--gooey-toast-text-subtle); margin-left: 2px;
        transition: transform 0.35s cubic-bezier(.4,0,.2,1);
    }
    .gooey-toast-expanded .gooey-toast-tab-chevron,
    .gooey-toast-collapsing .gooey-toast-tab-chevron { transform: rotate(180deg); }

    .gooey-toast-tab-close {
        width: 14px; height: 14px; color: var(--gooey-toast-text-subtle); margin-left: 4px;
        cursor: pointer; transition: color 0.15s; background: none; border: none; padding: 0;
    }
    .gooey-toast-tab-close:hover { color: var(--gooey-toast-text-muted); }

    .gooey-toast-body {
        position: relative;
        z-index: 1;
        background: var(--gooey-toast-bg);
        border-radius: 16px;
        width: 300px;
        padding: 0 20px;
        margin: 0 auto;
        text-align: left;
        opacity: 0;
        max-height: 0;
        overflow: hidden;
        transform: scaleY(0);
        transform-origin: top center;
        transition: opacity 0.35s cubic-bezier(.4,0,.2,1),
                    max-height 0.45s cubic-bezier(.4,0,.2,1),
                    transform 0.45s cubic-bezier(.4,0,.2,1),
                    margin-top 0.4s cubic-bezier(.4,0,.2,1),
                    padding 0.4s cubic-bezier(.4,0,.2,1);
    }
    .gooey-toast-expanded .gooey-toast-body {
        opacity: 1;
        max-height: 300px;
        margin-top: -6px;
        padding: 20px;
        transform: translateY(0) scaleY(1);
    }

    .gooey-toast-message { font-size: 13px; color: var(--gooey-toast-text); opacity: 0.7; line-height: 1.45; padding: 4px 0 6px; word-wrap: break-word; }
    .gooey-toast-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; font-size: 13px; }
    .gooey-toast-label { color: var(--gooey-toast-text-muted); }
    .gooey-toast-value { font-weight: 500; color: var(--gooey-toast-text); }
    .gooey-toast-sep { height: 1px; background: var(--gooey-toast-sep); margin: 6px 0; }
    .gooey-toast-footer { font-size: 12.5px; color: var(--gooey-toast-text-subtle); padding-top: 4px; }

    .gooey-toast-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 7px 0;
    }
    .gooey-toast-actions:has(.gooey-toast-action-btn:nth-child(2)) .gooey-toast-action-btn {
        flex: 1 1 0;
        min-width: 0;
    }
    .gooey-toast-action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        width: 100%;
        padding: 10px 15px;
        border-radius: 6px;
        border: none;
        background: var(--gooey-toast-action-bg);
        color: var(--gooey-toast-action-text);
        font-size: 13.5px;
        font-weight: 500;
        letter-spacing: -0.01em;
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
    }
    .gooey-toast-action-icon {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
    }
    .gooey-toast-action-btn:hover {
        background: var(--gooey-toast-action-bg-hover);
        color: var(--gooey-toast-action-text-hover);
    }
    .gooey-toast-action-btn.gooey-toast-action-confirm {
        pointer-events: none;
        opacity: 0.7;
    }

    .gooey-toast-border-tab {
        position: absolute; inset: 0; border-radius: 14px;
        border: 1px solid var(--gooey-toast-border); pointer-events: none; z-index: 3;
    }
    .gooey-toast-expanded .gooey-toast-border-tab,
    .gooey-toast-collapsing .gooey-toast-border-tab,
    .gooey-toast-has-timer .gooey-toast-border-tab,
    .gooey-toast-has-progress .gooey-toast-border-tab { border-color: transparent; }

    .gooey-toast-border-body {
        position: absolute; inset: 0; border-radius: 16px;
        border: 1px solid var(--gooey-toast-border); pointer-events: none; z-index: 3;
    }
    .gooey-toast-expanded .gooey-toast-border-body,
    .gooey-toast-collapsing .gooey-toast-border-body { border-color: transparent; }

    .gooey-toast-outline {
        position: absolute; inset: -1px; border-radius: 16px;
        border: 1px solid var(--gooey-toast-border); pointer-events: none;
        opacity: 0; transition: opacity 0.3s;
    }
    .gooey-toast-expanded .gooey-toast-outline,
    .gooey-toast-collapsing .gooey-toast-outline,
    .gooey-toast-has-timer .gooey-toast-outline,
    .gooey-toast-has-progress .gooey-toast-outline { opacity: 1; }

    .gooey-toast-light .gooey-toast-outline {
        opacity: 1;
    }
    .gooey-toast-light .gooey-toast-item {
        filter: drop-shadow(0 1px 3px rgba(0,0,0,0.08));
    }

    .gooey-toast-color-success { color: #22c55e; }
    .gooey-toast-color-error { color: #ef4444; }
    .gooey-toast-color-warning { color: #f59e0b; }
    .gooey-toast-color-info { color: #3b82f6; }
    .gooey-toast-color-loading { color: #888; }

    .gooey-toast-timer {
        position: relative;
        z-index: 0;
        background: var(--gooey-toast-bg);
        border-radius: 10px;
        margin: -8px auto 0;
        padding: 8px 10px;
        display: flex;
        width: fit-content;
        gap: 3px;
        align-items: flex-end;
        justify-content: center;
        height: 24px;
        transition: margin-top 0.45s cubic-bezier(.4,0,.2,1),
                    transform 0.45s cubic-bezier(.4,0,.2,1);
    }
    .gooey-toast-expanded .gooey-toast-timer,
    .gooey-toast-collapsing .gooey-toast-timer {
        margin-top: -8px;
    }
    .gooey-toast-timer-bar {
        width: 3px;
        height: 100%;
        border-radius: 1.5px;
        background: var(--timer-color);
        opacity: 0.5;
        transform-origin: bottom;
        animation: gooey-toast-bar-drain var(--bar-delay) linear forwards;
    }
    .gooey-toast-timer-bar:nth-child(odd) { height: 80%; }

    .gooey-toast-item:hover .gooey-toast-timer-bar {
        animation-play-state: paused;
    }

    .gooey-toast-timer-success { --timer-color: #22c55e; }
    .gooey-toast-timer-error   { --timer-color: #ef4444; }
    .gooey-toast-timer-warning { --timer-color: #f59e0b; }
    .gooey-toast-timer-info    { --timer-color: #3b82f6; }
    .gooey-toast-timer-loading { --timer-color: #888; }

    .gooey-toast-timer-border {
        position: absolute; inset: 0; border-radius: 10px;
        border: 1px solid var(--gooey-toast-border); pointer-events: none;
    }
    .gooey-toast-expanded .gooey-toast-timer-border,
    .gooey-toast-collapsing .gooey-toast-timer-border { border-color: transparent; }

    .gooey-toast-tab:focus-visible,
    .gooey-toast-tab-close:focus-visible,
    .gooey-toast-action-btn:focus-visible,
    .gooey-toast-undo-btn:focus-visible {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }

    .gooey-toast-item {
        touch-action: pan-y;
    }

    .gooey-toast-stacked {
        gap: 0 !important;
    }

    .gooey-toast-stack-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        background: var(--gooey-toast-bg);
        border: 1px solid var(--gooey-toast-border);
        border-radius: 10px;
        font-size: 12px;
        color: var(--gooey-toast-text-muted);
        margin-top: 6px;
    }

    .gooey-toast-progress {
        position: relative;
        z-index: 0;
        background: var(--gooey-toast-bg);
        border-radius: 10px;
        margin: -8px auto 0;
        padding: 8px 12px;
        width: 80px;
        transition: margin-top 0.45s cubic-bezier(.4,0,.2,1);
    }
    .gooey-toast-expanded .gooey-toast-progress {
        margin-top: -8px;
    }
    .gooey-toast-progress-track {
        height: 4px;
        border-radius: 2px;
        background: var(--gooey-toast-border);
        overflow: hidden;
    }
    .gooey-toast-progress-fill {
        height: 100%;
        border-radius: 2px;
        transition: width 0.3s ease;
    }
    .gooey-toast-progress-border {
        position: absolute; inset: 0; border-radius: 10px;
        border: 1px solid var(--gooey-toast-border); pointer-events: none;
    }
    .gooey-toast-expanded .gooey-toast-progress-border,
    .gooey-toast-collapsing .gooey-toast-progress-border { border-color: transparent; }

    .gooey-toast-undo-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        border: none;
        background: var(--gooey-toast-action-bg);
        color: var(--gooey-toast-action-text);
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.15s;
        margin-left: 4px;
        flex-shrink: 0;
    }
    .gooey-toast-undo-btn:hover {
        background: var(--gooey-toast-action-bg-hover);
    }

    @media (prefers-reduced-motion: reduce) {
        .gooey-toast-timer-bar {
            animation: none !important;
        }
    }

    @keyframes gooey-toast-bar-drain {
        0%, 80% { transform: scaleY(1); opacity: 0.5; }
        100%    { transform: scaleY(0); opacity: 0; }
    }

    @keyframes gooey-toast-enter {
        0%   { opacity: 0; transform: translateY(16px) scale(0.97); }
        100% { opacity: 1; transform: translateY(0) scale(1); }
    }
    @keyframes gooey-toast-enter-error {
        0%   { opacity: 0; transform: translateY(16px) scale(0.97); }
        40%  { opacity: 1; transform: translateY(0) scale(1); }
        50%  { transform: translateX(-5px); }
        60%  { transform: translateX(5px); }
        70%  { transform: translateX(-3px); }
        80%  { transform: translateX(3px); }
        90%  { transform: translateX(-1px); }
        100% { transform: translateX(0); }
    }
    @keyframes gooey-toast-enter-warning {
        0%   { opacity: 0; transform: translateY(16px) scale(0.97); }
        40%  { opacity: 1; transform: translateY(0) scale(1); }
        55%  { transform: translateY(-8px) scale(1); }
        70%  { transform: translateY(0) scale(1); }
        82%  { transform: translateY(-3px) scale(1); }
        100% { transform: translateY(0) scale(1); }
    }
    @keyframes gooey-toast-spin {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }
    @keyframes gooey-toast-exit {
        0%   { opacity: 1; transform: translateY(0) scale(1); }
        100% { opacity: 0; transform: translateY(10px) scale(0.97); }
    }
</style>
@endonce
