<div class="mb-4">

    @if ($request->request_for == 'Sales Delivery')
        <div class="mt-4">
            <h5 class="text-dark mb-3 fw-bold d-flex align-items-center">
                <i class="ti ti-paperclip me-2 text-primary"></i>
                Delivery Attachments
            </h5>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th class="text-muted fw-semibold" style="width: 60px;">No.</th>
                            <th class="text-muted fw-semibold">Customer Name</th>
                            <th class="text-muted fw-semibold">Products</th>
                            <th class="text-muted fw-semibold text-center" style="width: 120px;">Attachment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($request->attachments as $attachment)
                            @php
                                $filePath = public_path($attachment->attachment);
                                $imageUrl = file_exists($filePath) ? asset($attachment->attachment) : ('storage' . asset($attachment->attachment));
                            @endphp
                            <tr>
                                <td class="fw-semibold text-muted">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="ti ti-user text-primary me-2"></i>
                                        <span class="fw-medium">{{ $attachment->name }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-muted small">
                                        {!! nl2br(e($attachment->product_name)) !!}
                                    </div>
                                </td>
                                <td class="text-center">
                                    <a href="{{ $imageUrl }}" target="_blank" rel="noopener noreferrer"
                                        class="btn btn-primary btn-sm d-inline-flex align-items-center">
                                        <i class="ti ti-eye me-1"></i>
                                        <span>View</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($request->request_for == 'Office Supplies')
        <div class="mt-4">
            <h5 class="text-dark mb-3 fw-bold d-flex align-items-center">
                <i class="ti ti-shopping-cart me-2 text-primary"></i>
                List of Items
            </h5>

            @if ($request->lists->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-muted fw-semibold" style="width: 60px;">No.</th>
                                <th class="text-muted fw-semibold">Item Name</th>
                                <th class="text-muted fw-semibold" style="width: 120px;">Quantity</th>
                                <th class="text-muted fw-semibold text-end" style="width: 150px;">Price (TZS)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($request->lists as $item)
                                <tr>
                                    <td class="fw-semibold text-muted">{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="ti ti-box text-primary me-2"></i>
                                            <span class="fw-medium">{{ $item->item_name }}</span>
                                        </div>
                                    </td>
                                    <td class="fw-medium">{{ $item->quantity }}</td>
                                    <td class="text-end fw-semibold text-primary">{{ number_format($item->price) }}/=</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @elseif($request->request_for == 'Sales Delivery' || $request->request_for == 'Transport')
        <div class="mt-4">
            <h5 class="text-dark mb-3 fw-bold d-flex align-items-center">
                <i class="ti ti-map-pin me-2 text-primary"></i>
                Transport Route
            </h5>
            <div class="row g-3">
                @foreach ($request->trips as $trip)
                    <div class="col-sm-6 col-lg-3 mb-3">
                        <div class="card border shadow-none h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start">
                                    <div class="me-3">
                                        <div class="bg-success rounded-circle d-flex align-items-center justify-content-center"
                                             style="width: 40px; height: 40px;">
                                            <i class="ti ti-flag text-white"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted text-uppercase fw-semibold d-block mb-1">Collection Point</small>
                                        <h6 class="mb-0 fw-bold">{{ $trip->startPoint->name }}</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @foreach ($trip->stops as $stop)
                        <div class="col-sm-6 col-lg-3 mb-3">
                            <div class="card border shadow-none h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">
                                            <div class="bg-{{ $loop->last ? 'danger' : 'primary' }} rounded-circle d-flex align-items-center justify-content-center"
                                                 style="width: 40px; height: 40px;">
                                                <i class="ti ti-{{ $loop->last ? 'map-pin' : 'navigation' }} text-white"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted text-uppercase fw-semibold d-block mb-1">
                                                {{ $loop->last ? 'Destination' : 'Stop' }}
                                            </small>
                                            <h6 class="mb-0 fw-bold">{{ $stop->destination }}</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>

            <div class="mt-4">
                <h5 class="text-dark mb-3 fw-bold d-flex align-items-center">
                    <i class="ti ti-truck me-2 text-primary"></i>
                    Transport Mode
                </h5>
                <div class="card border shadow-none" style="max-width: 300px;">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <div class="bg-info rounded-circle d-flex align-items-center justify-content-center"
                                     style="width: 50px; height: 50px;">
                                    <i class="ti ti-truck text-white fs-5"></i>
                                </div>
                            </div>
                            <div>
                                <small class="text-muted text-uppercase fw-semibold d-block mb-1">Mode</small>
                                <h6 class="mb-0 fw-bold">{{ $request->transMode->name ?? 'N/A' }}</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($request->is_transporter == true)
        <div class="mt-4">
            <h5 class="text-dark mb-3 fw-bold d-flex align-items-center">
                <i class="ti ti-file me-2 text-primary"></i>
                Transport Attachment
            </h5>
            @if (!empty($request->attachment))
                <a href="{{ asset($request->attachment) }}" download
                    class="btn btn-primary d-inline-flex align-items-center shadow-sm">
                    <i class="ti ti-download me-2"></i>
                    <span>Download Attachment</span>
                </a>
            @else
                <div class="alert alert-danger d-flex align-items-center border-0">
                    <i class="ti ti-alert-circle me-2 fs-5"></i>
                    <span>No attachment available</span>
                </div>
            @endif
        </div>
    @elseif($request->request_for == 'Office Supplies')
        <div class="mt-4">
            <h5 class="text-dark mb-3 fw-bold d-flex align-items-center">
                <i class="ti ti-file me-2 text-primary"></i>
                Supporting Document
            </h5>
            @if (!empty($request->attachment))
                <a href="{{ asset($request->attachment) }}" download
                    class="btn btn-primary d-inline-flex align-items-center shadow-sm">
                    <i class="ti ti-download me-2"></i>
                    <span>Download Attachment</span>
                </a>
            @else
                <div class="alert alert-danger d-flex align-items-center border-0">
                    <i class="ti ti-alert-circle me-2 fs-5"></i>
                    <span>No attachment available</span>
                </div>
            @endif
        </div>
    @else
        @if (!empty($request->attachment))
            <div class="mt-4">
                <h5 class="text-dark mb-3 fw-bold d-flex align-items-center">
                    <i class="ti ti-file me-2 text-primary"></i>
                    Attachment
                </h5>
                <a href="{{ asset($request->attachment) }}" download
                    class="btn btn-primary d-inline-flex align-items-center shadow-sm">
                    <i class="ti ti-download me-2"></i>
                    <span>Download Attachment</span>
                </a>
            </div>
        @endif
    @endif

</div>

<style>
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
</style>
