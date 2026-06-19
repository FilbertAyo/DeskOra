@extends('mail.app')

@section('content')
    <div class="email-header">
        <h2>{{ $headline }}</h2>
    </div>
    <div>
        <p>Hello{{ !empty($recipientName) ? ' '.$recipientName : '' }},</p>
        <p>{{ $intro }}</p>
        @include('mail.requisitions._summary')
        <p>Please click the button below to review this requisition:</p>
        @include('mail.requisitions._action-button')
    </div>
@endsection
