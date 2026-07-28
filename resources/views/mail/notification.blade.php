@extends('mail.app')

@section('title', 'Welcome')

@section('content')
    <p class="eyebrow" style="margin:0 0 10px;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#71717a;">Account</p>
    <h1 class="title" style="margin:0 0 20px;font-family:Georgia,'Times New Roman',Times,serif;font-size:24px;line-height:1.3;font-weight:400;color:#18181b;">
        Welcome, {{ $name }}
    </h1>
    <p>Your registration is complete. Use the details below to sign in.</p>

    <table role="presentation" class="meta-table" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;margin:4px 0 20px;">
        <tr>
            <td class="meta-label" style="width:34%;padding:10px 12px 10px 0;border-bottom:1px solid #f4f4f5;color:#71717a;font-size:14px;">Email</td>
            <td class="meta-value" style="padding:10px 0;border-bottom:1px solid #f4f4f5;color:#18181b;font-size:14px;font-weight:600;">{{ $email }}</td>
        </tr>
        <tr>
            <td class="meta-label" style="width:34%;padding:10px 12px 10px 0;border-bottom:1px solid #f4f4f5;color:#71717a;font-size:14px;">Password</td>
            <td class="meta-value" style="padding:10px 0;border-bottom:1px solid #f4f4f5;color:#18181b;font-size:14px;font-weight:600;">
                @if (! empty($password))
                    {{ $password }}
                @else
                    Use the initial password set by your administrator. You can change it under Profile after signing in.
                @endif
            </td>
        </tr>
    </table>

    <p>Keep this information private.</p>
    @include('mail.partials.button', [
        'buttonUrl' => config('app.url'),
        'buttonLabel' => 'Sign in',
    ])
@endsection
