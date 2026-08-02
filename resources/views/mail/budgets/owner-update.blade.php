@extends('mail.app')

@section('title', $headline ?? 'Weekly budget update')

@section('content')
    <p class="eyebrow" style="margin:0 0 10px;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#71717a;">Weekly Budget</p>
    <h1 class="title" style="margin:0 0 20px;font-family:Georgia,'Times New Roman',Times,serif;font-size:24px;line-height:1.3;font-weight:400;color:#18181b;">{{ $headline }}</h1>
    <p>Hello{{ ! empty($recipientName) ? ' '.$recipientName : '' }},</p>
    <p>{{ $intro }}</p>
    <p><strong>Period:</strong> {{ $periodLabel }}</p>
    @if(!empty($itemLabel))
        <p><strong>Budget item:</strong> {{ $itemLabel }}</p>
    @endif
    @if(!empty($actorName))
        <p><strong>Processed by:</strong> {{ $actorName }}</p>
    @endif
    @if(!empty($note))
        <p><strong>Note:</strong> {{ $note }}</p>
    @endif
    <p style="margin:24px 0;">
        <a href="{{ $actionUrl }}" style="display:inline-block;padding:10px 18px;background:#18181b;color:#fff;text-decoration:none;border-radius:6px;">
            {{ $actionLabel }}
        </a>
    </p>
@endsection
