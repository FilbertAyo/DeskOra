@php
    $code = '419';
    $title = 'Page Expired';
    $message = 'Your session has expired. Please refresh the page and try again.';
@endphp

@extends('errors.layout')

@section('image')
    <img src="{{ asset('image/exp.jpg') }}" alt="Session expired" style="max-width: 280px; width: 100%; height: auto;">
@endsection

@section('actions')
    <a href="{{ route('login') }}" class="btn btn-primary">
        <i class="ti ti-login me-1"></i>
        Back to login
    </a>
@endsection
