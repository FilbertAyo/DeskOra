@if (! empty($comment))
    <div class="panel" style="background-color:#fafafa;border:1px solid #e4e4e7;border-radius:8px;padding:16px 18px;margin:18px 0;">
        <p class="panel-title" style="margin:0 0 8px;font-size:12px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#71717a;">
            Feedback
        </p>
        @if (! empty($actorName))
            <p style="margin:0 0 8px;font-size:13px;color:#71717a;">From {{ $actorName }}</p>
        @endif
        <p class="panel-body" style="margin:0;font-size:14px;line-height:1.6;color:#18181b;white-space:pre-wrap;">{{ $comment }}</p>
    </div>
@endif
