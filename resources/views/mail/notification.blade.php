@extends('mail.app')

@section('content')
    <div class="email-header">
        <h2>Welcome to Adilisha Portal, {{ $name }}!</h2>
    </div>
    <div>
        <p>Congratulations! Your registration is complete. Here are your login details:</p>

        <ul>
            <li><strong>Email:</strong> {{ $email }}</li>
            @if (! empty($password))
                <li><strong>Password:</strong> {{ $password }}</li>
            @else
                <li><strong>Password:</strong> Use the initial password your administrator set for you. You can change it anytime under Profile after you sign in.</li>
            @endif
        </ul>
        <p>You can now log in to your account using the link below:</p>
        <p>
            <a href="{{ config('app.url') }}"
                style="display: inline-block; padding: 10px 20px; background-color: #1d72b8; color: white; text-decoration: none; border-radius: 4px;">
                Login
            </a>
        </p>
        <p>Please keep this information secure and do not share it with anyone.</p>
    </div>
@endsection
