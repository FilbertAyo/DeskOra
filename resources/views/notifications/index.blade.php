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

    {{-- SMS Usage Info --}}
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-info mr-2"></i>About SMS Notifications
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>When will you receive SMS?</h6>
                            <ul class="mb-0">
                                <li>New petty cash request created</li>
                                <li>Request approved/rejected</li>
                                <li>Payment completed</li>
                                <li>Resubmission required</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Important Notes</h6>
                            <ul class="mb-0">
                                <li>SMS notifications respect your notification channel preference</li>
                                <li>Make sure your phone number is updated in your profile</li>
                                <li>Phone numbers are automatically normalized to Tanzanian format (255XXXXXXXXX)</li>
                                <li>Low balance warnings appear when balance is below 100 credits</li>
                            </ul>
                        </div>
                    </div>
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
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i>Save Reminder Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
