<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Edit Stock Item</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.stock-items.index') }}">Stock Items</a></li>
                                <li class="breadcrumb-item" aria-current="page">Edit</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.stock-items.index') }}" class="btn btn-sm btn-light"><i class="ti ti-arrow-left me-1"></i> Back</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('inventory.stock-items.update', $stockItem) }}">
        @csrf
        @method('PUT')
        @include('inventory.stock-items.partials.form-fields')
    </form>
    @include('inventory.partials.picker-scripts')
</x-app-layout>
