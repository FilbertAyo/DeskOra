@extends('mail.app')

@section('content')
    <div class="email-header">
        <h2>{{ $headline }}</h2>
    </div>
    <div>
        <p>Hello {{ $requesterName }},</p>
        <p>{{ $intro }}</p>
        @include('mail.requisitions._feedback')
        @include('mail.requisitions._summary')
        <p>Please review the feedback and update your requisition within 24 hours:</p>
        @include('mail.requisitions._action-button')
    </div>
@endsection
