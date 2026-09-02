@extends('mail.app')

@section('title', 'Your assigned inventory assets')

@section('content')
    <p class="eyebrow" style="margin:0 0 10px;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#71717a;">Inventory</p>
    <h1 class="title" style="margin:0 0 20px;font-family:Georgia,'Times New Roman',Times,serif;font-size:24px;line-height:1.3;font-weight:400;color:#18181b;">Assigned assets summary</h1>
    <p>Hello{{ ! empty($recipientName) ? ' '.$recipientName : '' }},</p>
    <p>
        You have <strong>{{ $assetCount }}</strong> inventory asset{{ $assetCount === 1 ? '' : 's' }} assigned to you
        (personally or to your department/unit).
    </p>
    <p>A CSV file listing these assets is attached to this email.</p>
    @include('mail.partials.button', [
        'buttonUrl' => $viewUrl,
        'buttonLabel' => 'View my assigned assets',
    ])
@endsection
