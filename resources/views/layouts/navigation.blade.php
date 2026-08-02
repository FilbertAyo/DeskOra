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
                 
                </li>

             
            </ul>
        </div>
        <!-- [Mobile Media Block end] -->

        @php
            $currentPeriod = $currentPeriod ?? null;
            $recentNotifications = Auth::user()->unreadNotifications()->latest()->limit(5)->get();
            $unreadNotificationCount = Auth::user()->unreadNotifications()->count();
        @endphp

        @if($currentPeriod)
            <div class="d-none d-lg-flex align-items-center gap-3">
                <div class="d-flex align-items-center text-muted">
                    <span class="small">
                        Week {{ $currentPeriod->week_number }} • {{ $currentPeriod->date_range }}
                    </span>
                    <span class="badge bg-light-{{ $currentPeriod->status === 'open' ? 'success' : 'secondary' }} border border-{{ $currentPeriod->status === 'open' ? 'success' : 'secondary' }} ml-2">
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
                <li class="pc-h-item d-none d-md-inline-flex align-items-center me-1">
                    <button
                        type="button"
                        class="btn btn-sm btn-light border d-inline-flex align-items-center gap-2"
                        data-deskora-go-open
                        title="DeskOra Go (Ctrl/Cmd + K)"
                    >
                        <i class="ti ti-search"></i>
                        <span class="small text-muted">Go</span>
                        <kbd class="small border rounded px-1 bg-white text-muted" style="font-size: 0.65rem;">⌘K</kbd>
                    </button>
                </li>
                <li class="pc-h-item d-md-none">
                    <a href="#" class="pc-head-link me-0" data-deskora-go-open title="DeskOra Go">
                        <i class="ti ti-search"></i>
                    </a>
                </li>
                <li class="dropdown pc-h-item">
                    <a class="pc-head-link dropdown-toggle arrow-none me-0"
                       data-bs-toggle="dropdown"
                       href="#"
                       role="button"
                       aria-haspopup="true"
                       aria-expanded="false"
                       title="Notifications">
                        <i class="ti ti-bell"></i>
                        @if($unreadNotificationCount > 0)
                            <span class="badge bg-light-danger border border-danger rounded-pill position-absolute" style="margin-left:-8px; margin-top:-8px;">
                                {{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}
                            </span>
                        @endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-end pc-h-dropdown" style="min-width: 340px;">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <h6 class="mb-0">Notifications</h6>
                            @if($unreadNotificationCount > 0)
                                <form action="{{ route('notifications.read-all') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">Mark all read</button>
                                </form>
                            @endif
                        </div>

                        @forelse($recentNotifications as $notification)
                            @php
                                $data = is_array($notification->data ?? null) ? $notification->data : [];
                                $title = $data['title'] ?? 'Notification';
                                $message = $data['message'] ?? 'You have a new update.';
                            @endphp
                            <a href="{{ route('notifications.open', $notification->id) }}"
                               class="dropdown-item bg-light">
                                <div class="fw-semibold text-dark">{{ $title }}</div>
                                <small class="text-muted d-block">{{ $message }}</small>
                                <small class="text-muted">{{ $notification->created_at?->diffForHumans() }}</small>
                            </a>
                        @empty
                            <div class="dropdown-item text-muted">No new notifications.</div>
                        @endforelse

                        <div class="border-top">
                            <a href="{{ route('notifications.inbox') }}" class="dropdown-item text-primary">
                                <i class="ti ti-list me-1"></i>View all notifications
                            </a>
                            @can('view notification settings')
                                <a href="{{ route('notification.index') }}" class="dropdown-item text-muted">
                                    <i class="ti ti-settings me-1"></i>Notification settings
                                </a>
                            @endcan
                        </div>
                    </div>
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

                   

                        <div class="tab-content" id="mysrpTabContent">
                            <div class="tab-pane fade show active" id="drp-tab-1" role="tabpanel" aria-labelledby="drp-t1"
                                tabindex="0">
                                <a href="{{ route('profile.edit') }}" class="dropdown-item">
                                    <i class="ti ti-edit-circle"></i>
                                    <span>Edit Profile</span>
                                </a>
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
