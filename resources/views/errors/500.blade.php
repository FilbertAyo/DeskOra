
@php
    $code = '500';
    $title = 'Internal Server Error';
    $message = "Something went wrong on our end. Please try again later.";
@endphp

@extends('errors.layout')

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">
        <i class="ti ti-home me-1"></i>
        Back Home
    </a>
@endsection
