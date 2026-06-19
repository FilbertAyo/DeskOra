@if (!empty($comment))
    <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 12px 16px; margin: 16px 0;">
        <p style="margin: 0 0 4px; font-weight: bold; color: #991b1b;">Feedback</p>
        @if (!empty($actorName))
            <p style="margin: 0 0 8px; font-size: 13px; color: #666;">From {{ $actorName }}</p>
        @endif
        <p style="margin: 0; white-space: pre-wrap;">{{ $comment }}</p>
    </div>
@endif
