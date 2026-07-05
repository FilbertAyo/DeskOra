<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Notifications</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">Notifications</li>
                            </ul>
                        </div>

                        @if($unreadCount > 0)
                            <div class="col-auto">
                                <form action="{{ route('notifications.read-all') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-checks me-1"></i> Mark all as read
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-bell me-2"></i>Your notifications
                    </h5>
                    @if($unreadCount > 0)
                        <span class="badge bg-light-danger border border-danger">{{ $unreadCount }} unread</span>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($notifications->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($notifications as $notification)
                                @php
                                    $data = is_array($notification->data ?? null) ? $notification->data : [];
                                    $title = $data['title'] ?? 'Notification';
                                    $message = $data['message'] ?? 'You have a new update.';
                                    $isUnread = $notification->read_at === null;
                                @endphp
                                <div class="list-group-item {{ $isUnread ? 'bg-light' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <a href="{{ route('notifications.open', $notification->id) }}"
                                                   class="fw-semibold text-dark text-decoration-none">
                                                    {{ $title }}
                                                </a>
                                                @if($isUnread)
                                                    <span class="badge bg-light-primary border border-primary">New</span>
                                                @endif
                                            </div>
                                            <p class="text-muted mb-1 small">{{ $message }}</p>
                                            <small class="text-muted">
                                                {{ $notification->created_at?->format('M d, Y H:i') }}
                                                ({{ $notification->created_at?->diffForHumans() }})
                                            </small>
                                        </div>
                                        <div class="d-flex flex-column gap-1">
                                            <a href="{{ route('notifications.open', $notification->id) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                Open
                                            </a>
                                            @if($isUnread)
                                                <form action="{{ route('notifications.read', $notification->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-light w-100">Mark read</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="p-3 border-top">
                            {{ $notifications->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="ti ti-bell-off text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3 mb-0">You have no notifications yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @can('view notification settings')
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="text-end">
                    <a href="{{ route('notification.index') }}" class="btn btn-sm btn-light">
                        <i class="ti ti-settings me-1"></i> Notification settings (admin)
                    </a>
                </div>
            </div>
        </div>
    @endcan
</x-app-layout>
