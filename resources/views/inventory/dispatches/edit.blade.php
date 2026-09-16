<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Edit {{ $dispatch->dispatch_number }}</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('inventory.dispatches.index') }}">Dispatch</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.dispatches.show', $dispatch) }}">{{ $dispatch->title }}</a></li>
                                <li class="breadcrumb-item" aria-current="page">Edit</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.dispatches.show', $dispatch) }}" class="btn btn-sm btn-light">
                            <i class="ti ti-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('inventory.dispatches.update', $dispatch) }}">
        @csrf
        @method('PUT')
        <div class="card shadow-none border">
            <div class="card-body">
                @include('inventory.dispatches.partials.form-fields', ['dispatch' => $dispatch])
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('inventory.dispatches.show', $dispatch) }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary" data-busy-label="Saving…">Save changes</button>
        </div>
    </form>
</x-app-layout>
