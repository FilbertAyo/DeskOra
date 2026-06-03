<x-app-layout>
    <div class="row align-items-center mb-4">
        <div class="col">
            <h2 class="h3 mb-0 page-title">
                <i class="ti ti-bell me-2"></i>Notification settings
            </h2>
            <p class="text-muted mt-1 mb-0">Configure weekly submission reminders and grace period alerts for staff.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-4">
            <i class="ti ti-check me-1"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4">
            <i class="ti ti-alert-circle me-1"></i>{{ session('error') }}
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning mb-4">
            <i class="ti ti-alert-triangle me-1"></i>{{ session('warning') }}
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-none border h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-message-circle me-2"></i>SMS balance
                    </h5>
                </div>
                <div class="card-body">
                    @if($smsInfo && isset($smsInfo['status']) && $smsInfo['status'])
                        <p class="text-muted small mb-2">Petty cash SMS credits (Beem)</p>
                        <h3 class="mb-2 {{ $smsInfo['balance'] < 100 ? 'text-danger' : ($smsInfo['balance'] < 500 ? 'text-warning' : 'text-success') }}">
                            {{ number_format($smsInfo['balance']) }}
                        </h3>
                        @if($smsInfo['balance'] < 100)
                            <div class="alert alert-warning mb-0 py-2 small">
                                <i class="ti ti-alert-triangle me-1"></i>Low balance — please top up.
                            </div>
                        @else
                            <span class="badge bg-success">
                                <i class="ti ti-check-circle me-1"></i>Connected
                            </span>
                        @endif
                    @else
                        <div class="alert alert-warning mb-0 small">
                            <i class="ti ti-alert-triangle me-1"></i>
                            SMS balance unavailable. Check Beem credentials in environment settings.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @can('manage notifications')
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card shadow-none border h-100">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="ti ti-clock-hour-4 me-2 text-warning"></i>Grace period alerts
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">
                            <strong>Email only</strong> — sent when you extend a plan or report deadline and staff still
                            have not <strong>submitted</strong> that weekly plan or report (drafts still receive a reminder).
                            Goes to every active staff member with an email address, not SMS profile settings.
                        </p>
                        <p class="small text-muted">
                            Mail driver: <code>{{ config('mail.default') }}</code>
                            @if(config('mail.default') === 'log')
                                <span class="text-danger">— emails are written to the log only until SMTP is configured in <code>.env</code>.</span>
                            @endif
                        </p>

                        <form action="{{ route('notification.grace-period.update') }}" method="POST" class="row g-3">
                            @csrf
                            @method('PUT')

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="grace_enabled" name="enabled" value="1"
                                        {{ old('enabled', $graceConfig->enabled) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="grace_enabled">Enable grace period email alerts</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <p class="mb-2 small text-muted">
                                    Last email batch:
                                    @if($lastGraceSentAt)
                                        {{ \Carbon\Carbon::parse($lastGraceSentAt)->timezone(config('app.timezone'))->format('M d, Y H:i') }}
                                    @else
                                        Not sent yet
                                    @endif
                                </p>
                                <button type="submit" class="btn btn-warning">
                                    <i class="ti ti-device-floppy me-1"></i>Save grace alerts
                                </button>
                            </div>
                        </form>

                        <form action="{{ route('notification.grace-period.test-email') }}" method="POST" class="row g-2 align-items-end mt-3 border-top pt-3">
                            @csrf
                            <div class="col-sm-8">
                                <label for="test_email" class="form-label small mb-1">Send test email</label>
                                <input type="email" name="test_email" id="test_email" class="form-control form-control-sm"
                                    value="filbertayo09@gmail.com" placeholder="you@example.com" required>
                            </div>
                            <div class="col-sm-4">
                                <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="ti ti-mail me-1"></i>Send test
                                </button>
                            </div>
                        </form>

                        <form action="{{ route('notification.grace-period.run-now') }}" method="POST" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                <i class="ti ti-send me-1"></i>Run grace emails now
                            </button>
                        </form>
                        <p class="small text-muted mt-2 mb-0">
                            Run now emails all staff who still owe a plan or report for weeks in grace (re-sends even if notified before).
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-none border h-100">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="ti ti-calendar-event me-2"></i>Weekly submission reminders
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">
                            Regular scheduled reminder for staff who have not submitted their weekly plan or report
                            (separate from grace period extensions).
                        </p>

                        <form action="{{ route('notification.weekly-reminder.update') }}" method="POST" class="row g-3">
                            @csrf
                            @method('PUT')

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1"
                                        {{ old('enabled', $reminderConfig->enabled) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="enabled">Enable scheduled reminders</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="day_of_week" class="form-label">Day</label>
                                <select id="day_of_week" name="day_of_week" class="form-control">
                                    @foreach([0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'] as $dayValue => $dayLabel)
                                        <option value="{{ $dayValue }}" {{ (int) old('day_of_week', $reminderConfig->day_of_week) === $dayValue ? 'selected' : '' }}>
                                            {{ $dayLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="time" class="form-label">Time</label>
                                <input id="time" type="time" name="time" class="form-control" value="{{ old('time', $reminderConfig->time) }}">
                                <small class="text-muted">{{ config('app.timezone') }}</small>
                            </div>

                            <div class="col-md-6">
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
                                    <label class="form-check-label" for="channel_in_app">In-app notification</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <p class="mb-2 small text-muted">
                                    Sends separate reminders for each missing activity (plan and report).
                                    Last sent:
                                    @if($lastReminderSentAt)
                                        {{ \Carbon\Carbon::parse($lastReminderSentAt)->timezone(config('app.timezone'))->format('M d, Y H:i') }}
                                    @else
                                        Not sent yet
                                    @endif
                                </p>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-device-floppy me-1"></i>Save reminders
                                </button>
                            </div>
                        </form>

                        <form action="{{ route('notification.weekly-reminder.run-now') }}" method="POST" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="ti ti-send me-1"></i>Run reminders now
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-warning mb-4">
            <i class="ti ti-lock me-1"></i>
            You can view these settings, but only users with <strong>manage notifications</strong> can change them.
        </div>
    @endcan

    <div class="row">
        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-history me-2"></i>Recent deliveries
                    </h5>
                </div>
                <div class="card-body">
                    @if($recentReminderLogs->isEmpty())
                        <div class="alert alert-info mb-0">
                            No notification deliveries logged yet.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Sent at</th>
                                        <th>Type</th>
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
                                                <span class="badge {{ ($log->dispatch_type_label ?? '') === 'Grace period' ? 'bg-warning text-dark' : 'bg-light text-dark border' }}">
                                                    {{ $log->dispatch_type_label ?? 'Reminder' }}
                                                </span>
                                            </td>
                                            <td>
                                                <div>{{ $log->user?->name ?? 'Unknown' }}</div>
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
