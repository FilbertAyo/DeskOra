@extends('mail.app')

@section('title', $headline ?? 'Requisition rejected')

@section('content')
    <p class="eyebrow" style="margin:0 0 10px;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#71717a;">Requisition</p>
    <h1 class="title" style="margin:0 0 20px;font-family:Georgia,'Times New Roman',Times,serif;font-size:24px;line-height:1.3;font-weight:400;color:#18181b;">{{ $headline }}</h1>
    <p>Hello {{ $requesterName }},</p>
    <p>{{ $intro }}</p>
    @include('mail.requisitions._feedback')
    @include('mail.requisitions._summary')
    <p>View the requisition details below.</p>
    @include('mail.requisitions._action-button')
@endsection
