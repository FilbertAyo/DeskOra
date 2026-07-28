@extends('mail.app')

@section('title', 'Petty cash rejected')

@section('content')
    <p class="eyebrow" style="margin:0 0 10px;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#71717a;">Petty cash</p>
    <h1 class="title" style="margin:0 0 20px;font-family:Georgia,'Times New Roman',Times,serif;font-size:24px;line-height:1.3;font-weight:400;color:#18181b;">
        Request rejected
    </h1>
    <p>Hello {{ $name }},</p>
    <p>Your petty cash request for <strong>{{ $reason }}</strong> has been rejected.</p>
    <p>Open the request to review the details.</p>
    @include('mail.partials.button', [
        'buttonUrl' => config('app.url').'/petty/'.$id,
        'buttonLabel' => 'View details',
    ])
@endsection
