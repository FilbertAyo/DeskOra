<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Register Asset</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.assets.index') }}">Assets</a></li>
                                <li class="breadcrumb-item" aria-current="page">Create</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.assets.index') }}" class="btn btn-sm btn-light"><i class="ti ti-arrow-left me-1"></i> Back</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('inventory.assets.store') }}">
        @csrf
        @include('inventory.assets.partials.form-fields')
    </form>
</x-app-layout>
