<nav class="pc-sidebar">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="{{ route('dashboard') }}" class="b-brand text-primary">
                <img src="{{ asset('image/logo-dark.png') }}" class="img-fluid logo-lg" alt="logo">
            </a>
        </div>

        <div class="navbar-content">
            <ul class="pc-navbar">
                <!-- Dashboard -->
                <li class="pc-item {{ Request::routeIs('dashboard') ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
                        <span class="pc-mtext">Dashboard</span>
                    </a>
                </li>

                <!-- Weekly Plans & Reports -->
                <li class="pc-item pc-caption">
                    <label>Activities</label>
                    <i class="ti ti-calendar"></i>
                </li>

                <li class="pc-item pc-hasmenu {{ Request::routeIs('documents.weekly-plans') || Request::routeIs('documents.weekly-reports') || Request::routeIs('documents.reviewer-dashboard') ? 'active pc-trigger' : '' }}">
                    <a href="javascript:void(0);" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-calendar"></i></span>
                        <span class="pc-mtext">Weekly Activities</span>
                        <span class="pc-arrow"><i class="ti ti-chevron-down"></i></span>
                    </a>
                    <ul class="pc-submenu">
                        <li class="{{ Request::routeIs('documents.weekly-plans') ? 'active' : '' }}">
                            <a href="{{ route('documents.weekly-plans') }}" class="pc-link">
                             <span class="pc-mtext">Weekly Plans</span>
                            </a>
                        </li>
                        <li class="{{ Request::routeIs('documents.weekly-reports') ? 'active' : '' }}">
                            <a href="{{ route('documents.weekly-reports') }}" class="pc-link">
                                <span class="pc-mtext">Weekly Reports</span>
                            </a>
                        </li>
                        @if(Auth::user()->hasPermissionTo('view all documents') || Auth::user()->hasPermissionTo('approve documents'))
                            <li class="{{ Request::routeIs('documents.reviewer-dashboard') ? 'active' : '' }}">
                                <a href="{{ route('documents.reviewer-dashboard') }}" class="pc-link">
                                   <span class="pc-mtext">Review Submissions</span>
                                    @php
                                        $submittedCount = \App\Models\Document::submitted()->count();
                                    @endphp
                                    @if($submittedCount > 0)
                                        <span class="badge bg-primary ml-2">{{ $submittedCount }}</span>
                                    @endif
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>

        
                <li
                    class="pc-item {{ Request::routeIs('documents.index') && request('type') === 'monthly_report' ? 'active' : '' }}">
                    <a href="{{ route('documents.index', ['type' => 'monthly_report']) }}" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-folder"></i></span>
                        <span class="pc-mtext">Monthly Reports Archive</span>
                    </a>
                </li>
                <li class="pc-item {{ Request::routeIs('documents.index') && !request()->has('type') ? 'active' : '' }}">
                    <a href="{{ route('documents.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-stack"></i></span>
                        <span class="pc-mtext">All Documents</span>
                    </a>
                </li>

                <!-- Action Log -->
                @if(Auth::user()->hasAnyPermission(['create action logs', 'approve documents']))
                    <li class="pc-item {{ Request::routeIs('action-logs.index') ? 'active' : '' }}">
                        <a href="{{ route('action-logs.index') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-clipboard"></i></span>
                            <span class="pc-mtext">Action Log</span>
                        </a>
                    </li>
                @endif

                <!-- Petty Cash -->
                <li class="pc-item pc-caption">
                    <label>Petty Cash</label>
                    <i class="ti ti-file-text"></i>
                </li>

                <li class="pc-item {{ Request::routeIs('petty.index') ? 'active' : '' }}">
                    <a href="{{ route('petty.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-file-text"></i></span>
                        <span class="pc-mtext">Petty Cash Requests</span>
                    </a>
                </li>

                @can('first pettycash approval')
                    <li class="pc-item">
                        <a href="{{ route('petty.list') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-users"></i></span>
                            <span class="pc-mtext">Approvals</span>
                            @if ($pendingPettiesCount > 0)
                                <span class="badge bg-danger">{{ $pendingPettiesCount }}</span>
                            @endif
                        </a>
                    </li>
                @endcan

                <!-- Finance & Transactions -->
                @can('view cashflow movements')
                    <li
                        class="pc-item pc-hasmenu {{ Request::routeIs('petty.cashier') || Request::routeIs('deposit.index') || Request::routeIs('cashflow.index') ? 'pc-triggered' : '' }}">
                        <a href="#!" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-credit-card"></i></span>
                            <span class="pc-mtext">Finance & Transactions</span>
                            <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                        </a>
                        <ul class="pc-submenu">
                            <li class="pc-item">
                                <a href="{{ route('petty.cashier') }}" class="pc-link">
                                    <span class="pc-mtext">Payments</span>
                                    @if ($approvedPettiesCount > 0)
                                        <span class="badge bg-success">{{ $approvedPettiesCount }}</span>
                                    @endif
                                </a>
                            </li>
                            <li class="pc-item">
                                <a href="{{ route('deposit.index') }}" class="pc-link">
                                    <span class="pc-mtext">Cash Management</span>
                                </a>
                            </li>
                            <li class="pc-item">
                                <a href="{{ route('cashflow.index') }}" class="pc-link">
                                    <span class="pc-mtext">Cash Flow</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                @endcan

                <!-- Reports and Settings -->
                <li class="pc-item pc-caption">
                    <label>Reports and Settings</label>
                    <i class="ti ti-settings"></i>
                </li>

                <li class="pc-item {{ Request::routeIs('work-periods.*') ? 'active' : '' }}">
                    <a href="{{ route('work-periods.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-calendar"></i></span>
                        <span class="pc-mtext">Manage Work Periods</span>
                    </a>
                </li>

                @can('view reports')
                    <li class="pc-item {{ Request::routeIs('reports') ? 'active' : '' }}">
                        <a href="{{ route('reports') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-box"></i></span>
                            <span class="pc-mtext">Reports</span>
                        </a>
                    </li>
                @endcan

                @can('view settings')
                    <li
                        class="pc-item pc-hasmenu {{ Request::routeIs('admin.index') || Request::routeIs('departments') || Request::routeIs('settings.*') ? 'pc-triggered' : '' }}">
                        <a href="#!" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-settings"></i></span>
                            <span class="pc-mtext">Settings</span>
                            <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                        </a>
                        <ul class="pc-submenu">
                            <li class="pc-item {{ Request::routeIs('admin.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.index') }}" class="pc-link">
                                    <span class="pc-mtext">Users</span>
                                </a>
                            </li>
                            <li class="pc-item {{ Request::routeIs('departments') ? 'active' : '' }}">
                                <a href="{{ route('departments') }}" class="pc-link">
                                    <span class="pc-mtext">Departments</span>
                                </a>
                            </li>
                            <li class="pc-item {{ Request::routeIs('notification.index') ? 'active' : '' }}">
                                <a href="{{ route('notification.index') }}" class="pc-link">
                                    <span class="pc-mtext">Notifications</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                @endcan
            </ul>
        </div>
    </div>
</nav>
