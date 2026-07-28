@extends('mail.app')

@section('title', 'Grace period active')

@section('content')
    <p class="eyebrow" style="margin:0 0 10px;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#71717a;">Deadline</p>
    <h1 class="title" style="margin:0 0 20px;font-family:Georgia,'Times New Roman',Times,serif;font-size:24px;line-height:1.3;font-weight:400;color:#18181b;">
        Grace period is active
    </h1>
    <p>Hello {{ $staffName }},</p>
    <p>
        Your administrator extended the deadline for your <strong>{{ $activityLabel }}</strong>
        for <strong>{{ $periodLabel }}</strong>.
    </p>
    <p>
        You can still create or submit until
        <strong>{{ $graceEndsAt->format('M d, Y H:i') }}</strong>.
    </p>
    @include('mail.partials.button', [
        'buttonUrl' => $actionUrl,
        'buttonLabel' => 'Open and submit',
    ])
    <p style="margin-top:18px;font-size:13px;color:#71717a;">
        If you have already submitted, you can ignore this message.
    </p>
@endsection
