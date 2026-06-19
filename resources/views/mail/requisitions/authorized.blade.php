@extends('mail.app')

@section('content')
    <div class="email-header">
        <h2>{{ $headline }}</h2>
    </div>
    <div>
        <p>Hello{{ !empty($recipientName) ? ' '.$recipientName : '' }},</p>
        <p>{{ $intro }}</p>
        @if (!empty($actorName))
            <p>Authorized by <strong>{{ $actorName }}</strong>.</p>
        @endif
        @include('mail.requisitions._summary')
        <p>You can view the full requisition details using the button below:</p>
        @include('mail.requisitions._action-button')
    </div>
@endsection
