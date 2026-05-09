<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Year Plans</h5>
                            <ul class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">Year Plans</li>
                            </ul>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createYearPlanModal">
                            <i class="ti ti-circle-plus me-1"></i> Add year plan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="ti ti-chart-line me-2"></i>Plans by year</h5>
                </div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Year</th>
                                    <th class="text-center">Activities</th>
                                    <th class="text-center" style="width: 140px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($plans as $plan)
                                    <tr>
                                        <td class="fw-semibold">{{ $plan->year }}</td>
                                        <td class="text-center">{{ $plan->activities_count }}</td>
                                        <td class="text-center">
                                            <a href="{{ route('year-plan.show', $plan->year) }}" class="btn btn-sm btn-primary">
                                                <i class="ti ti-eye me-1"></i> Open
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            No year plans yet. Create one for the current or upcoming year.
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

    <div class="modal fade" id="createYearPlanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="{{ route('year-plan.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ti ti-calendar-plus me-2"></i>New year plan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Calendar year <span class="text-danger">*</span></label>
                        <input type="number" name="year" class="form-control @error('year') is-invalid @enderror"
                            min="2000" max="2100" value="{{ old('year', (int) date('Y')) }}" required>
                        @error('year')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <p class="text-muted small mt-2 mb-0">One plan per year. Activities are grouped by sub-department.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($errors->has('year'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const el = document.getElementById('createYearPlanModal');
                if (el && typeof bootstrap !== 'undefined') {
                    new bootstrap.Modal(el).show();
                }
            });
        </script>
    @endif
</x-app-layout>
