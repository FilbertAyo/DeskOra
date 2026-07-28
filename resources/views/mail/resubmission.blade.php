@extends('mail.app')

@section('title', 'Petty cash resubmitted')

@section('content')
    <p class="eyebrow" style="margin:0 0 10px;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#71717a;">Petty cash</p>
    <h1 class="title" style="margin:0 0 20px;font-family:Georgia,'Times New Roman',Times,serif;font-size:24px;line-height:1.3;font-weight:400;color:#18181b;">
        Resubmission from {{ $name }}
    </h1>
    <p>Hello,</p>
    <p>The petty cash request for <strong>{{ $reason }}</strong> has been resubmitted and is ready for review.</p>
    @include('mail.partials.button', [
        'buttonUrl' => config('app.url').'/pettycash/request/'.$id.'/details',
        'buttonLabel' => 'View details',
    ])
@endsection
