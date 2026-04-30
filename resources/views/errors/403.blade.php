@php
    $code = '403';
    $title = 'Permission Denied';
    $message = "It seems like you don't have access to perform this action.";
@endphp

@extends('errors.layout')

@section('actions')
    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i>
        Go Back
    </a>
    <a href="{{ url('/') }}" class="btn btn-primary">
        <i class="ti ti-home me-1"></i>
        Dashboard
    </a>
@endsection
