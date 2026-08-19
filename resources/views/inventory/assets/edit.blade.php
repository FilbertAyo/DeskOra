<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Edit Asset</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.assets.show', $asset) }}">{{ $asset->asset_code }}</a></li>
                                <li class="breadcrumb-item" aria-current="page">Edit</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.assets.show', $asset) }}" class="btn btn-sm btn-light"><i class="ti ti-arrow-left me-1"></i> Back</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('inventory.assets.update', $asset) }}">
        @csrf
        @method('PUT')
        @include('inventory.assets.partials.form-fields')
    </form>
</x-app-layout>
