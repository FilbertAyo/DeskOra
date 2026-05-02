<x-app-layout>
    <div class="row align-items-center mb-4">
        <div class="col">
            <h2 class="h3 mb-0 page-title">
                <i class="ti ti-bell mr-2"></i>Notifications
            </h2>
            <p class="text-muted mt-1 mb-0">Manage how you receive notifications</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-4">
            <i class="ti ti-check me-1"></i>{{ session('success') }}
        </div>
    @endif

    <div class="row mb-4">
        {{-- SMS Balance Card --}}
        <div class="col-md-6">
            <div class="card shadow-none border">
                <div class="card-header text-white">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-message-circle mr-2"></i>SMS Account Information
                    </h5>
                </div>
                <div class="card-body">
                    @if($smsInfo && isset($smsInfo['status']) && $smsInfo['status'])
                        <div class="mb-3">
                            <label class="text-muted small">SMS Balance</label>
                            <div class="d-flex align-items-center">
                                <h3 class="mb-0 mr-2 {{ $smsInfo['balance'] < 100 ? 'text-danger' : ($smsInfo['balance'] < 500 ? 'text-warning' : 'text-success') }}">
                                    {{ number_format($smsInfo['balance']) }}
                                </h3>
                            </div>
                            @if($smsInfo['balance'] < 100)
                                <div class="alert alert-warning mt-2 mb-0 py-2">
                                    <i class="ti ti-alert-triangle mr-1"></i>
                                    <small>Low SMS balance. Please top up your account.</small>
                                </div>
                            @endif
                        </div>

                        <hr>

                        <div class="mb-2">
                            <label class="text-muted small">Sender ID</label>
                            <p class="mb-0 font-weight-bold">{{ $smsInfo['sender_id'] ?? 'N/A' }}</p>
                        </div>

                        <div class="mb-2">
                            <label class="text-muted small">API Key</label>
                            <p class="mb-0 font-monospace small">{{ $smsInfo['api_key'] ?? 'N/A' }}</p>
                        </div>

                        <div class="mt-3">
                            <span class="badge bg-success">
                                <i class="ti ti-check-circle mr-1"></i>Connected
                            </span>
                        </div>
                    @else
                        <div class="alert alert-warning mb-0">
                            <i class="ti ti-alert-triangle mr-2"></i>
                            Unable to retrieve SMS balance. Please check your Beem API credentials.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Notification Preferences Card --}}
        <div class="col-md-6">
            <div class="card shadow-none border">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-settings mr-2"></i>Notification Preferences
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-3">Manage how you receive notifications for petty cash requests and other updates.</p>
                    <a class="btn btn-primary" href="{{ route('profile.edit') }}">
                        <i class="ti ti-settings mr-1"></i>Go to Profile Settings
                    </a>

                    <hr class="my-4">

                    <h6 class="mb-3">Notification Channels</h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="ti ti-check text-success mr-2"></i>
                            <strong>SMS:</strong> Receive text messages on your phone
                        </li>
                        <li class="mb-2">
                            <i class="ti ti-check text-success mr-2"></i>
                            <strong>Email:</strong> Receive email notifications
                        </li>
                        <li class="mb-2">
                            <i class="ti ti-check text-success mr-2"></i>
                            <strong>Both:</strong> Receive notifications via SMS and Email
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

  

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-calendar-event mr-2"></i>Reminder Automation
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Configure automated reminders for staff who have not submitted required weekly activities.
                    </p>

                    @can('manage notifications')
                    <form action="{{ route('notification.weekly-reminder.update') }}" method="POST" class="row g-3">
                        @csrf
                        @method('PUT')

                        <div class="col-md-3">
                            <label class="form-label">Enable Reminder</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1"
                                    {{ old('enabled', $reminderConfig->enabled) ? 'checked' : '' }}>
                                <label class="form-check-label" for="enabled">Active</label>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label for="day_of_week" class="form-label">Day</label>
                            <select id="day_of_week" name="day_of_week" class="form-control">
                                @foreach([0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'] as $dayValue => $dayLabel)
                                    <option value="{{ $dayValue }}" {{ (int) old('day_of_week', $reminderConfig->day_of_week) === $dayValue ? 'selected' : '' }}>
                                        {{ $dayLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="time" class="form-label">Time</label>
                            <input id="time" type="time" name="time" class="form-control" value="{{ old('time', $reminderConfig->time) }}">
                            <small class="text-muted">Timezone: {{ config('app.timezone') }}</small>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label d-block">Channels</label>
                            @php
                                $selectedChannels = old('channels', $reminderConfig->channels ?? []);
                            @endphp
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="channels[]" id="channel_email" value="email"
                                    {{ in_array('email', $selectedChannels, true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="channel_email">Email</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="channels[]" id="channel_in_app" value="in_app"
                                    {{ in_array('in_app', $selectedChannels, true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="channel_in_app">In-app Notification</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info mb-3">
                                Separate reminders are sent for each missing activity (weekly plan and weekly report).
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-device-floppy me-1"></i>Save Reminder Settings
                                </button>
                            </div>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div>
                            <h6 class="mb-1">Reminder Delivery Health</h6>
                            <p class="mb-0 text-muted small">
                                Last sent:
                                @if($lastReminderSentAt)
                                    {{ \Carbon\Carbon::parse($lastReminderSentAt)->timezone(config('app.timezone'))->format('M d, Y H:i') }}
                                @else
                                    Never sent yet
                                @endif
                            </p>
                        </div>
                        <form action="{{ route('notification.weekly-reminder.run-now') }}" method="POST" class="mt-2 mt-md-0">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="ti ti-send me-1"></i>Run Reminder Now
                            </button>
                        </form>
                    </div>
                    @else
                    <div class="alert alert-warning mb-0">
                        <i class="ti ti-lock me-1"></i>
                        You can view notification settings, but only users with <strong>manage notifications</strong> permission can configure reminder automation.
                    </div>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-history mr-2"></i>Recent Reminder Deliveries
                    </h5>
                </div>
                <div class="card-body">
                    @if($recentReminderLogs->isEmpty())
                        <div class="alert alert-info mb-0">
                            No reminder deliveries logged yet.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Sent At</th>
                                        <th>User</th>
                                        <th>Activity</th>
                                        <th>Status</th>
                                        <th>Channels</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentReminderLogs as $log)
                                        <tr>
                                            <td>{{ $log->created_at?->timezone(config('app.timezone'))->format('M d, Y H:i') }}</td>
                                            <td>
                                                <div>{{ $log->user?->name ?? 'Unknown user' }}</div>
                                                <small class="text-muted">{{ $log->user?->email }}</small>
                                            </td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $log->activity_type)) }}</td>
                                            <td>
                                                <span class="badge {{ $log->status === 'sent' ? 'bg-success' : 'bg-warning text-dark' }}">
                                                    {{ ucfirst(str_replace('_', ' ', $log->status)) }}
                                                </span>
                                            </td>
                                            <td>{{ implode(', ', $log->channels ?? []) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
