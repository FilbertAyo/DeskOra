<x-app-layout>
    <div class="row align-items-center mb-4">
        <div class="col">
            <h2 class="h3 mb-0 page-title">
                <i class="ti ti-bell mr-2"></i>Notifications
            </h2>
            <p class="text-muted mt-1 mb-0">Manage how you receive notifications</p>
        </div>
    </div>

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
</x-app-layout>
