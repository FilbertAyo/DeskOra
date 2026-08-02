<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">My Approval Queue</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">Approvals</li>
                            </ul>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-light-danger border border-danger" id="approval-queue-total-badge" data-approval-queue-total>
                                {{ (int) ($summary['total'] ?? 0) }} pending
                            </span>
                            <button type="button" class="btn btn-sm btn-light" onclick="window.location.reload()" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <div class="btn-group" role="group" aria-label="Filter approval queue">
                <a href="{{ route('approvals.queue', ['filter' => 'all']) }}"
                   class="btn btn-sm {{ ($filter ?? 'all') === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    All ({{ (int) ($summary['total'] ?? 0) }})
                </a>
                <a href="{{ route('approvals.queue', ['filter' => 'finance']) }}"
                   class="btn btn-sm {{ ($filter ?? '') === 'finance' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Finance ({{ (int) ($summary['finance'] ?? 0) }})
                </a>
                <a href="{{ route('approvals.queue', ['filter' => 'budget']) }}"
                   class="btn btn-sm {{ ($filter ?? '') === 'budget' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Budget ({{ (int) ($summary['budget'] ?? 0) }})
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Item</th>
                                    <th>From</th>
                                    <th>Your stage</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $item)
                                    <tr>
                                        <td>
                                            @if (($item['kind'] ?? '') === 'budget')
                                                <span class="badge bg-light-warning border border-warning text-dark">Budget</span>
                                            @else
                                                <span class="badge bg-light-primary border border-primary">Finance</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $item['title'] }}</div>
                                            <div class="small text-muted">{{ $item['type_label'] ?? '' }}</div>
                                        </td>
                                        <td>{{ $item['requested_by'] ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge bg-light-secondary border border-secondary">
                                                {{ $item['stage_label'] ?? '—' }}
                                            </span>
                                        </td>
                                        <td>
                                            @if (isset($item['amount']) && $item['amount'] !== null)
                                                {{ number_format((float) $item['amount'], 2) }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $item['date_label'] ?? '' }}</td>
                                        <td class="text-end">
                                            <a href="{{ $item['url'] }}" class="btn btn-sm btn-outline-primary">Open</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-5">
                                            <i class="ti ti-checks fs-1 d-block mb-2"></i>
                                            Nothing waiting for your approval right now.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
