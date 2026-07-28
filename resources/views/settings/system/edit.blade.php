<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">System configuration</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript:void(0)">Settings</a></li>
                                <li class="breadcrumb-item" aria-current="page">System</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-circle me-2"></i>{{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.system.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card shadow-none border mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="ti ti-forms me-2"></i>Forms</h5>
                <p class="text-muted small mb-0 mt-1">
                    Settings used on printable and downloadable forms. More form options can be added here later.
                </p>
            </div>
            <div class="card-body">
                <div class="row align-items-start">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <label class="form-label fw-semibold">Forms logo</label>
                        <p class="text-muted small mb-2">
                            Shown at the top of forms such as the Fund Requisition Form.
                            @if ($usingDefaultLogo)
                                Currently using the default application logo.
                            @endif
                        </p>
                        <div class="border rounded p-3 bg-light text-center">
                            <img src="{{ $formsLogoUrl }}" alt="Forms logo" class="img-fluid" style="max-height: 120px;">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="forms_logo" class="form-label">Upload new logo</label>
                            <input type="file" class="form-control" id="forms_logo" name="forms_logo" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                            <div class="form-text">PNG or JPG recommended. Max 2MB.</div>
                            @error('forms_logo')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        @unless ($usingDefaultLogo)
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="1" id="remove_forms_logo" name="remove_forms_logo">
                                <label class="form-check-label" for="remove_forms_logo">
                                    Reset to default logo
                                </label>
                            </div>
                        @endunless
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white border-top d-flex justify-content-end gap-2">
                <x-primary-button label="Save changes" />
            </div>
        </div>
    </form>
</x-app-layout>
