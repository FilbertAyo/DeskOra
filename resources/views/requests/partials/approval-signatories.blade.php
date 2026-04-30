<div class="row mt-4">
    <div class="col-md-12 mb-3">
        <div class="row g-2">
            <div class="col-md-4">
                <span class="d-block">Requested by:
                    <span class="border-bottom d-inline-block ms-1 px-2" style="min-width: 180px;">
                        {{ $request->user->name ?? '' }}
                    </span>
                </span>
            </div>
            <div class="col-md-4">
                <span class="d-block">Title:
                    <span class="border-bottom d-inline-block ms-1 px-2" style="min-width: 160px;">
                        {{ $request->user->title ?? '' }}
                    </span>
                </span>
            </div>
            <div class="col-md-4">
                <span class="d-block">Signature:
                    <span class="border-bottom d-inline-flex align-items-end ms-1 px-2" style="min-width: 160px; min-height: 40px;">
                        @if ($request->user?->signature)
                            <img src="{{ asset($request->user->signature) }}" style="max-height:38px;">
                        @endif
                    </span>
                </span>
            </div>
        </div>
    </div>

    <div class="col-md-12 mb-3">
        <div class="row g-2">
            <div class="col-md-4">
                <span class="d-block">Reviewed by:
                    <span class="border-bottom d-inline-block ms-1 px-2" style="min-width: 180px;">
                        {{ $request->reviewedBy->name ?? '' }}
                    </span>
                </span>
            </div>
            <div class="col-md-4">
                <span class="d-block">Title:
                    <span class="border-bottom d-inline-block ms-1 px-2" style="min-width: 160px;">
                        {{ $request->reviewedBy->title ?? '' }}
                    </span>
                </span>
            </div>
            <div class="col-md-4">
                <span class="d-block">Signature:
                    <span class="border-bottom d-inline-flex align-items-end ms-1 px-2" style="min-width: 160px; min-height: 40px;">
                        @if ($request->reviewedBy?->signature)
                            <img src="{{ asset($request->reviewedBy->signature) }}" style="max-height:38px;">
                        @endif
                    </span>
                </span>
            </div>
        </div>
    </div>

    <div class="col-md-12 mb-3">
        <div class="row g-2">
            <div class="col-md-4">
                <span class="d-block">Approved by:
                    <span class="border-bottom d-inline-block ms-1 px-2" style="min-width: 180px;">
                        {{ $request->approvedBy->name ?? '' }}
                    </span>
                </span>
            </div>
            <div class="col-md-4">
                <span class="d-block">Title:
                    <span class="border-bottom d-inline-block ms-1 px-2" style="min-width: 160px;">
                        {{ $request->approvedBy->title ?? '' }}
                    </span>
                </span>
            </div>
            <div class="col-md-4">
                <span class="d-block">Signature:
                    <span class="border-bottom d-inline-flex align-items-end ms-1 px-2" style="min-width: 160px; min-height: 40px;">
                        @if ($request->approvedBy?->signature)
                            <img src="{{ asset($request->approvedBy->signature) }}" style="max-height:38px;">
                        @endif
                    </span>
                </span>
            </div>
        </div>
    </div>

    <div class="col-md-12 mb-3">
        <div class="row g-2">
            <div class="col-md-4">
                <span class="d-block">Authorized by:
                    <span class="border-bottom d-inline-block ms-1 px-2" style="min-width: 180px;">
                        {{ $request->authorizedBy->name ?? '' }}
                    </span>
                </span>
            </div>
            <div class="col-md-4">
                <span class="d-block">Title:
                    <span class="border-bottom d-inline-block ms-1 px-2" style="min-width: 160px;">
                        {{ $request->authorizedBy->title ?? '' }}
                    </span>
                </span>
            </div>
            <div class="col-md-4">
                <span class="d-block">Signature:
                    <span class="border-bottom d-inline-flex align-items-end ms-1 px-2" style="min-width: 160px; min-height: 40px;">
                        @if ($request->authorizedBy?->signature)
                            <img src="{{ asset($request->authorizedBy->signature) }}" style="max-height:38px;">
                        @endif
                    </span>
                </span>
            </div>
        </div>
    </div>
</div>
