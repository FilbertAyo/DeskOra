@php
    use App\Support\DocumentUpdateMessages;

    $code = '403';
    $defaultTitle = 'Permission Denied';
    $defaultMessage = "It seems like you don't have access to perform this action.";
    $exceptionMessage = isset($exception) ? trim((string) $exception->getMessage()) : '';
    $message = ($exceptionMessage !== '' && $exceptionMessage !== 'This action is unauthorized.')
        ? $exceptionMessage
        : $defaultMessage;
    $title = DocumentUpdateMessages::isClosedPeriodMessage($message)
        ? 'Editing no longer available'
        : $defaultTitle;
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
