@php
    /** @var \App\Data\WeeklySubmissionSlot|null $slot */
    $slot = $slot ?? null;
    $documentType = $documentType ?? \App\Models\Document::TYPE_WEEKLY_PLAN;
    $returnTo = $returnTo ?? request()->fullUrl();
    $size = $size ?? 'sm';
    $btnClass = $btnClass ?? ($documentType === \App\Models\Document::TYPE_WEEKLY_PLAN ? 'btn-info' : 'btn-primary');
    $disabledHint = $disabledHint ?? null;
    $disabledLabel = $disabledLabel ?? ($documentType === \App\Models\Document::TYPE_WEEKLY_PLAN
        ? 'Weekly plan window closed'
        : 'Weekly report window closed');
@endphp

@if($slot && $slot->showAction())
    <a href="{{ $slot->actionUrl($documentType, $returnTo) }}" class="btn {{ $btnClass }} btn-{{ $size }}">
        <i class="ti {{ $slot->actionIcon() }} me-1"></i>{{ $slot->actionLabel($documentType) }}
    </a>
@elseif($disabledHint)
    <button type="button" class="btn btn-light btn-{{ $size }}" disabled title="{{ $disabledHint }}">
        <i class="ti ti-calendar-off me-1"></i>{{ $disabledLabel }}
    </button>
@endif
