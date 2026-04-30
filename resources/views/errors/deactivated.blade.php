@php
    $code = 'Account';
    $title = 'Account Deactivated';
    $message = 'Your account is currently deactivated. Please contact an administrator for assistance.';
@endphp

@extends('errors.layout')

@section('actions')
    <a href="https://wa.me/+255755237692" target="_blank" rel="noopener noreferrer" class="btn btn-success">
        <i class="ti ti-brand-whatsapp me-1"></i>
        Contact on WhatsApp
    </a>
@endsection
