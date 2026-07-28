@extends('mail.app')

@section('title', 'Submission reminder')

@section('content')
    <p class="eyebrow" style="margin:0 0 10px;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#71717a;">Reminder</p>
    <h1 class="title" style="margin:0 0 20px;font-family:Georgia,'Times New Roman',Times,serif;font-size:24px;line-height:1.3;font-weight:400;color:#18181b;">
        Submission reminder
    </h1>
    <p>Hello {{ $staffName }},</p>
    <p>
        Your <strong>{{ $activityLabel }}</strong> for <strong>{{ $periodLabel }}</strong>
        has not been submitted yet.
    </p>
    <p>Please submit it in the system when you can.</p>
    @include('mail.partials.button', [
        'buttonUrl' => $documentsUrl,
        'buttonLabel' => 'Open documents',
    ])
@endsection
