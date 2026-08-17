@php
    $canDownloadNow = $user->can('download', $letter);
    $downloadStatus = $letter->downloadAuthorizationStatus();
    $buttonClass = $compact ?? false ? 'btn btn-sm btn-danger' : 'btn btn-sm btn-danger';
    $pendingButtonClass = $compact ?? false ? 'btn btn-sm btn-outline-warning' : 'btn btn-sm btn-outline-warning';
    $iconOnly = $compact ?? false;
@endphp

@if($canDownloadNow)
    <a href="{{ route('official-letters.download', $letter) }}" class="{{ $buttonClass }}" title="Download PDF">
        <i class="ti ti-download{{ $iconOnly ? '' : ' me-1' }}"></i>@if(! $iconOnly) Download PDF @endif
    </a>
@elseif($downloadStatus === 'pending')
    <button type="button"
            class="{{ $pendingButtonClass }}"
            title="Awaiting download authorization"
            data-bs-toggle="modal"
            data-bs-target="#letterDownloadPendingModal"
            data-letter-reference="{{ $letter->reference_number }}">
        <i class="ti ti-download{{ $iconOnly ? '' : ' me-1' }}"></i>@if(! $iconOnly) Download PDF @endif
    </button>
@else
    <button type="button"
            class="{{ $buttonClass }}"
            title="Request download authorization"
            data-bs-toggle="modal"
            data-bs-target="#letterDownloadRequestModal"
            data-request-url="{{ route('official-letters.request-download', $letter) }}"
            data-letter-reference="{{ $letter->reference_number }}"
            data-download-status="{{ $downloadStatus ?? '' }}">
        <i class="ti ti-download{{ $iconOnly ? '' : ' me-1' }}"></i>@if(! $iconOnly) Download PDF @endif
    </button>
@endif
