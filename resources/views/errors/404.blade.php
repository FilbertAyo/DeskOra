@php
    $code = '404';
    $title = 'Page Not Found';
    $message = "The page you are looking for doesn't exist or may have been moved.";
@endphp

@extends('errors.layout')

@section('image')
    <img src="{{ asset('image/404error.png') }}" alt="404 error" style="max-width: 280px; width: 100%; height: auto;">
@endsection
