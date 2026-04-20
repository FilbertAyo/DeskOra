<header class="pc-header">
    <div class="header-wrapper">
        <!-- [Mobile Media Block] start -->
        <div class="me-auto pc-mob-drp">
            <ul class="list-unstyled">
                <!-- ======= Menu collapse Icon ===== -->
                <li class="pc-h-item pc-sidebar-collapse">
                    <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                        <i class="ti ti-menu-2"></i>
                    </a>
                </li>
                <li class="pc-h-item pc-sidebar-popup">
                    <a href="#" class="pc-head-link ms-0" id="mobile-collapse">
                        <i class="ti ti-menu-2"></i>
                    </a>
                </li>

                <li class="dropdown pc-h-item d-inline-flex d-md-none">
                    <a class="pc-head-link dropdown-toggle arrow-none m-0" data-bs-toggle="dropdown" href="#"
                        role="button" aria-haspopup="false" aria-expanded="false">
                        <i class="ti ti-search"></i>
                    </a>
                    <div class="dropdown-menu pc-h-dropdown drp-search">
                        <form class="px-3">
                            <div class="form-group mb-0 d-flex align-items-center">
                                <i data-feather="search"></i>
                                <input type="search" class="form-control border-0 shadow-none"
                                    placeholder="Search here. . .">
                            </div>
                        </form>
                    </div>
                </li>

                <li class="pc-h-item d-none d-md-inline-flex">
                    <form class="header-search">
                        <i data-feather="search" class="icon-search"></i>
                        <input type="search" class="form-control" placeholder="Search here. . .">
                    </form>
                </li>
            </ul>
        </div>
        <!-- [Mobile Media Block end] -->

        @php
            $currentPeriod = $currentPeriod ?? null;
            $matrixRows = $matrixRows ?? collect();
        @endphp

        @if($currentPeriod)
            <div class="d-none d-lg-flex align-items-center gap-3">
                <div class="d-flex align-items-center text-muted">
                    <span class="small">
                        Week {{ $currentPeriod->week_number }} • {{ $currentPeriod->date_range }}
                    </span>
                    <span class="badge badge-{{ $currentPeriod->status === 'open' ? 'success' : 'secondary' }} ml-2">
                        {{ ucfirst($currentPeriod->status) }}
                    </span>
                </div>
            </div>
        @else
            <div class="d-none d-lg-flex align-items-center gap-3">
                <div class="d-flex align-items-center text-muted">
                    <i class="ti ti-calendar me-2"></i>
                    <span class="small">No active work period</span>
                </div>
            </div>
        @endif

        <div class="ms-auto">
            <ul class="list-unstyled">
                @can('manage anonymous feedback')
                    <li class="dropdown pc-h-item">
                        <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                            role="button" aria-haspopup="false" aria-expanded="false" title="Resources">
                            <i class="ti ti-book"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                            <a href="{{ route('anonymous-feedback.index') }}" class="dropdown-item">
                                <i class="ti ti-message-circle me-2"></i>
                                Anonymous feedback inbox
                            </a>
                        </div>
                    </li>
                @endcan

                <li class="pc-h-item">
                    <button type="button" class="pc-head-link border-0 bg-transparent me-0"
                        data-bs-toggle="offcanvas" data-bs-target="#weeklyObjectiveMatrix"
                        aria-controls="weeklyObjectiveMatrix" title="Weekly Objective Matrix">
                        <i class="ti ti-layout-grid"></i> 
                    </button>
                </li>

                <li class="dropdown pc-h-item header-user-profile">
                    <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                        role="button" aria-haspopup="false" data-bs-auto-close="outside" aria-expanded="false">
                        <img src="{{ asset(Auth::user()->file ?? 'assets/images/profile.png') }}" alt="user-image" class="user-avtar">
                        <span>{{ Auth::user()->name }}</span>
                    </a>

                    <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
                        <div class="dropdown-header">
                            <div class="d-flex mb-1">
                                <div class="flex-shrink-0">
                                    <img src="{{ asset(Auth::user()->file ?? 'assets/images/profile.png') }}" alt="user-image"
                                        class="user-avtar wid-35">
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">{{ Auth::user()->name }}</h6>
                                    <span>Account</span>
                                </div>
                            </div>
                        </div>

                        <ul class="nav drp-tabs nav-fill nav-tabs" id="mydrpTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="drp-t1" data-bs-toggle="tab" data-bs-target="#drp-tab-1"
                                    type="button" role="tab" aria-controls="drp-tab-1" aria-selected="true">
                                    <i class="ti ti-user"></i> Profile
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="drp-t2" data-bs-toggle="tab" data-bs-target="#drp-tab-2"
                                    type="button" role="tab" aria-controls="drp-tab-2" aria-selected="false">
                                    <i class="ti ti-settings"></i> Settings
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="mysrpTabContent">
                            <div class="tab-pane fade show active" id="drp-tab-1" role="tabpanel" aria-labelledby="drp-t1"
                                tabindex="0">
                                <a href="{{ route('profile.edit') }}" class="dropdown-item">
                                    <i class="ti ti-edit-circle"></i>
                                    <span>Edit Profile</span>
                                </a>
                            </div>

                            <div class="tab-pane fade" id="drp-tab-2" role="tabpanel" aria-labelledby="drp-t2"
                                tabindex="0">
                             

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="ti ti-power text-danger"></i>
                                        <span>Logout</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</header>

<div class="offcanvas offcanvas-end" tabindex="-1" id="weeklyObjectiveMatrix"
    aria-labelledby="weeklyObjectiveMatrixLabel" style="width: min(95vw, 1200px);">
    <div class="offcanvas-header border-bottom">
        <div>
            <h5 class="offcanvas-title mb-1" id="weeklyObjectiveMatrixLabel">Weekly Objective Matrix</h5>
            @if($currentPeriod)
                <p class="text-muted mb-0 small">
                    Week {{ $currentPeriod->week_number }} - {{ $currentPeriod->date_range }}
                </p>
            @else
                <p class="text-muted mb-0 small">No active work period</p>
            @endif
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body">
        @if(!$currentPeriod)
            <div class="alert alert-warning mb-0">
                <i class="ti ti-alert-triangle me-1"></i>
                There is no active work period yet.
            </div>
        @else
            @php
                $submittedCount = $matrixRows->count();
            @endphp

            @if($submittedCount === 0)
                <div class="alert alert-info mb-0">
                    <i class="ti ti-info-circle me-1"></i>
                    No submitted weekly plans with a filled main objective found for this week.
                </div>
            @else
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="{{ route('documents.weekly-objective-matrix.download', ['orientation' => 'portrait']) }}"
                        class="btn btn-danger btn-sm">
                        <i class="ti ti-download me-1"></i> Download PDF 
                    </a>
                   
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 240px;">Staff</th>
                                <th style="width: 220px;">Department</th>
                                <th>Main Objective</th>
                                <th>Progress / Reflection</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matrixRows as $plan)
                                @php
                                    $planContent = is_array($plan->content ?? null) ? $plan->content : [];
                                    $objective = is_array($planContent['objective'] ?? null) ? $planContent['objective'] : [];
                                    $reflectionProgress = trim((string) ($objective['reflection_progress'] ?? ''));
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $plan->user?->name ?? 'Unknown Staff' }}</td>
                                    <td>{{ $plan->user?->department?->name ?? 'Unknown Sub Department' }}</td>
                                    <td>{{ $plan->main_target }}</td>
                                    <td>{{ $reflectionProgress !== '' ? $reflectionProgress : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</div>
