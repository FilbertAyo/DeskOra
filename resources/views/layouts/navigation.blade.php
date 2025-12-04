<nav class="navbar navbar-light bg-white border mb-4">

    <button type="button" class="navbar-toggler text-muted mt-2 p-0 mr-3 collapseSidebar">
        <i class="fe fe-menu navbar-toggler-icon"></i>
    </button>

    <div class="d-none d-lg-flex align-items-center gap-3">
    
        @php
            $currentPeriod = \App\Models\WorkPeriod::getCurrent();
        @endphp
        
        @if($currentPeriod)
            <div class="d-flex align-items-center text-muted">
                <span class="small">
                    Week {{ $currentPeriod->week_number }} • {{ $currentPeriod->date_range }}
                </span>
                <span class="badge badge-{{ $currentPeriod->status === 'open' ? 'success' : 'secondary' }} ml-2">
                    {{ ucfirst($currentPeriod->status) }}
                </span>
            </div>
        @else
            <div class="d-flex align-items-center text-muted">
                <i class="fe fe-calendar me-2"></i>
                <span class="small">No active work period</span>
            </div>
        @endif
    </div>


    <ul class="nav ms-auto align-items-center">
        <li class="nav-item dropdown">
            <a class="nav-link text-muted" href="#" role="button" data-toggle="dropdown" aria-expanded="false">
                <i class="fe fe-layers"></i>
            </a>

            <div class="dropdown-menu dropdown-menu-end p-3 shadow-lg border-0 rounded-3" style="min-width: 250px;">
                <div class="mb-2">
                    <h6 class="fw-bold mb-0">Quick Actions</h6>
                    <small class="text-muted">Shortcuts</small>
                </div>

                <div class="row g-2">
                    @can('request pettycash')
                        <div class="col-12">
                            <a href="{{ route('petty.create') }}"
                                class="btn btn-light d-flex align-items-center justify-content-start py-2">
                                <i class="fe fe-file-text fs-4 text-warning mr-2"></i>
                                <span>New Petty Cash</span>
                            </a>
                        </div>
                    @endcan

                    @can('view reports')
                        <div class="col-12">
                            <a href="{{ route('reports') }}"
                                class="btn btn-light d-flex align-items-center justify-content-start py-2">
                                <i class="fe fe-book fs-4 text-info mr-2"></i>
                                <span>Reports</span>
                            </a>
                        </div>
                    @endcan

                    @can('view cashflow movements')
                        <div class="col-12">
                            <a href="{{ route('deposit.index') }}"
                                class="btn btn-light d-flex align-items-center justify-content-start py-2">
                                <i class="fe fe-credit-card fs-4 text-secondary mr-2"></i>
                                <span>Payments</span>
                            </a>
                        </div>
                    @endcan

                    {{-- ✅ New Notifications Feature --}}
                    <div class="col-12">
                        <a href="{{ route('notification.index') }}"
                            class="btn btn-light d-flex align-items-center justify-content-start py-2">
                            <i class="fe fe-bell fs-4 text-primary mr-2"></i>
                            <span>Notifications</span>
                        </a>
                    </div>

                </div>
            </div>
        </li>


        <li class="nav-item">
            <a class="nav-link text-muted d-flex align-items-center" href="{{ route('profile.edit') }}">
                <span class="avatar avatar-sm m-1"
                    style="width: 32px; height: 32px; overflow: hidden; display: inline-block;">
                    <img src="{{ asset(Auth::user()->file ?? 'image/prof.jpeg') }}" alt="..."
                        class="avatar-img rounded-circle"
                        style="width: 100%; height: 100%; object-fit: cover; object-position: center; display: block;">
                </span>
                <span class="fw-bold">{{ Auth::user()->name }}</span>
            </a>
        </li>
    </ul>
</nav>
