<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">{{ $session->session_number }}</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('inventory.verification.index') }}">Verification</a></li>
                                <li class="breadcrumb-item" aria-current="page">{{ $session->name }}</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.verification.index') }}" class="btn btn-sm btn-light">Back</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card shadow-none border mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <div>
                    <div class="fw-semibold">{{ $session->name }}</div>
                    <div class="small text-muted">
                                        Scope: {{ $session->location?->pathLabel() ?? $session->department?->name ?? 'All assets' }}
                        · Started by {{ $session->startedBy?->name ?? '—' }}
                    </div>
                </div>
                <span class="badge bg-light-secondary">
                    {{ $statusOptions[$session->status] ?? $session->status }}
                </span>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('inventory.verification.results', $session) }}">
        @csrf
        @method('PUT')
        <div class="card shadow-none border">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>Asset</th>
                                <th>Current</th>
                                <th>Result</th>
                                <th>New location / person</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($results as $result)
                                <tr>
                                    <td>
                                        <a href="{{ route('inventory.assets.show', $result->asset) }}">{{ $result->asset?->asset_code }}</a>
                                        <div class="small text-muted">{{ $result->asset?->name }}</div>
                                    </td>
                                    <td class="small">
                                        {{ $result->asset?->location?->pathLabel() ?? '—' }}<br>
                                        {{ $result->asset?->responsibleUser?->name ?? '—' }}
                                    </td>
                                    <td>
                                        @if($session->isOpen())
                                            <select name="results[{{ $result->id }}][result]" class="form-control form-control-sm">
                                                @foreach($resultOptions as $value => $label)
                                                    <option value="{{ $value }}" @selected(old("results.{$result->id}.result", $result->result) === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            {{ $resultOptions[$result->result] ?? $result->result }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($session->isOpen())
                                            <select name="results[{{ $result->id }}][recorded_location_id]" class="form-control form-control-sm mb-1">
                                                <option value="">Location (if changed)</option>
                                                @foreach($locations as $location)
                                                    <option value="{{ $location->id }}" @selected(old("results.{$result->id}.recorded_location_id", $result->recorded_location_id) == $location->id)>{{ $location->pathLabel() }}</option>
                                                @endforeach
                                            </select>
                                            <select name="results[{{ $result->id }}][recorded_responsible_user_id]" class="form-control form-control-sm">
                                                <option value="">Person (if changed)</option>
                                                @foreach($users as $user)
                                                    <option value="{{ $user->id }}" @selected(old("results.{$result->id}.recorded_responsible_user_id", $result->recorded_responsible_user_id) == $user->id)>{{ $user->name }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <div class="small">{{ $result->recordedLocation?->pathLabel() ?? '—' }}</div>
                                            <div class="small">{{ $result->recordedResponsibleUser?->name ?? '—' }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($session->isOpen())
                                            <input type="text" name="results[{{ $result->id }}][notes]" class="form-control form-control-sm" value="{{ old("results.{$result->id}.notes", $result->notes) }}">
                                        @else
                                            {{ $result->notes ?: '—' }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No assets in this session scope.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($session->isOpen() && $results->isNotEmpty())
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary btn-sm">Save results</button>
                    </div>
                @endif
            </div>
        </div>
    </form>

    @if($session->isOpen() && $results->isNotEmpty())
        @can('complete', $session)
            <form method="POST" action="{{ route('inventory.verification.complete', $session) }}" class="mt-3" onsubmit="return confirm('Complete this session and apply missing / location / person / damaged updates?');">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">Complete session</button>
            </form>
        @endcan
    @endif
</x-app-layout>
