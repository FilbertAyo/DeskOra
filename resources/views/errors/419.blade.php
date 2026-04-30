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
    <a href="{{ url()->current() }}" class="btn btn-outline-secondary">
        <i class="ti ti-refresh me-1"></i>
        Refresh
    </a>
    <a href="{{ url('/') }}" class="btn btn-primary">
        <i class="ti ti-home me-1"></i>
        Back Home
    </a>
@endsection
