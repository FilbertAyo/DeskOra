<x-app-layout>


    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Profile Settings</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">Profile Settings</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="reloadPage()" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-none border mb-4">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div>
                    <h5 class="mb-1">Keep your profile up to date</h5>
                    <p class="text-muted mb-0 small">Update contacts and signature to improve report and approval workflow.</p>
                </div>
                <span class="badge bg-light-primary border border-primary mt-2 mt-md-0">Account Security Enabled</span>
            </div>
        </div>
    </div>


    <!-- Profile Information Section -->
    <div class="mb-4">
        @include('profile.partials.update-profile-information-form')
    </div>

    <div class="card shadow-none border mb-4">
        <div class="card-header bg-white border-bottom">
            <h5 class="card-title mb-0">Signature</h5>
            <p class="text-muted small mb-0">Used on reports and approvals</p>
        </div>
        <div class="card-body p-4">
            @include('profile.partials.upload-signature-form')
        </div>
    </div>

 

    <style>
        .icon-square {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .bg-success-light {
            background-color: rgba(16, 185, 129, 0.1);
        }

        .bg-danger-light {
            background-color: rgba(239, 68, 68, 0.1);
        }

        .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important;
        }

        .btn-white {
            background-color: #fff;
            border: 1px solid #e5e7eb;
        }

        .btn-white:hover {
            background-color: #f9fafb;
            border-color: #d1d5db;
        }
    </style>

    <script>
        function reloadPage() {
            window.location.reload();
        }
    </script>

</x-app-layout>
