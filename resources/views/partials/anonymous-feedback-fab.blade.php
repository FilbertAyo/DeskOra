@can('manage anonymous feedback')
    @if (($newAnonymousFeedbackCount ?? 0) > 0 && ! request()->routeIs('anonymous-feedback.*'))
        <a href="{{ route('anonymous-feedback.index', ['status' => 'new']) }}"
           class="anonymous-feedback-fab"
           aria-label="{{ $newAnonymousFeedbackCount }} new anonymous feedback{{ $newAnonymousFeedbackCount === 1 ? '' : 's' }}"
           title="New anonymous feedback">
            <i class="ti ti-inbox" aria-hidden="true"></i>
            <span class="anonymous-feedback-fab__badge">
                {{ $newAnonymousFeedbackCount > 99 ? '99+' : $newAnonymousFeedbackCount }}
            </span>
        </a>

        <style>
            .anonymous-feedback-fab {
                position: fixed;
                left: clamp(0.75rem, 3vw, 1.25rem);
                bottom: clamp(0.75rem, 3vw, 1.25rem);
                z-index: 1045;
                width: 3.25rem;
                height: 3.25rem;
                border-radius: 50%;
                background: #dc3545;
                color: #fff;
                box-shadow: 0 4px 14px rgba(220, 53, 69, 0.45);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                transition: transform 0.15s ease, box-shadow 0.15s ease;
            }

            .anonymous-feedback-fab:hover,
            .anonymous-feedback-fab:focus {
                color: #fff;
                transform: scale(1.05);
                box-shadow: 0 6px 18px rgba(220, 53, 69, 0.55);
            }

            .anonymous-feedback-fab i {
                font-size: 1.35rem;
                line-height: 1;
            }

            .anonymous-feedback-fab__badge {
                position: absolute;
                top: -0.2rem;
                right: -0.2rem;
                min-width: 1.25rem;
                height: 1.25rem;
                padding: 0 0.3rem;
                border-radius: 999px;
                background: #fff;
                color: #dc3545;
                border: 2px solid #dc3545;
                font-size: 0.7rem;
                font-weight: 700;
                line-height: 1.05rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
        </style>
    @endif
@endcan
