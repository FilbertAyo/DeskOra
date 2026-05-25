@php
    $code = '503';
    $title = 'Under Maintenance';
    $message = "The system is currently down for maintenance. Please check back soon.";
@endphp

@extends('errors.layout')

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">
        <i class="ti ti-home me-1"></i>
        Back Home
    </a>
@endsection