<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Request Disposal</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('inventory.disposal.index') }}">Disposal</a></li>
                                <li class="breadcrumb-item" aria-current="page">Create</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.disposal.index') }}" class="btn btn-sm btn-light">Back</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-none border">
                <div class="card-body">
                    <p class="text-muted">Disposal requires review. The asset stays in the register after approval, with status Disposed and editing locked.</p>
                    <form method="POST" action="{{ route('inventory.disposal.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Asset <span class="text-danger">*</span></label>
                            <select name="asset_id" class="form-control" required>
                                <option value="">-- Select asset --</option>
                                @foreach($assets as $option)
                                    <option value="{{ $option->id }}" @selected(old('asset_id', $asset?->id) == $option->id)>
                                        {{ $option->asset_code }} — {{ $option->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Method <span class="text-danger">*</span></label>
                            <select name="method" class="form-control" required>
                                @foreach($methodOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" rows="4" class="form-control" required>{{ old('reason') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit for review</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
