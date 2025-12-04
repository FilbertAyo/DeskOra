<x-app-layout>
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">
                        <i class="fe fe-clipboard me-1"></i> Weekly Minutes
                    </a>
                </li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="{{ route('minutes.create') }}" class="btn mb-2 btn-primary btn-sm">
                <span class="btn-label">
                    <i class="fe fe-plus-circle"></i>
                </span>
                New Minutes
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fe fe-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fe fe-alert-circle me-2"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="row my-2">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0"><i class="fe fe-list me-2"></i>Weekly Minutes List</h5>
                        </div>
                        <div class="col-md-6 text-right">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="{{ route('minutes.index') }}" 
                                   class="btn {{ !request('state') ? 'btn-primary' : 'btn-light' }}">
                                    All
                                </a>
                                <a href="{{ route('minutes.index', ['state' => 'draft']) }}" 
                                   class="btn {{ request('state') == 'draft' ? 'btn-primary' : 'btn-light' }}">
                                    Drafts
                                </a>
                                <a href="{{ route('minutes.index', ['state' => 'submitted']) }}" 
                                   class="btn {{ request('state') == 'submitted' ? 'btn-primary' : 'btn-light' }}">
                                    Submitted
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if($documents->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No.</th>
                                        <th>Title</th>
                                        <th>State</th>
                                        <th>Created</th>
                                        <th class="text-center" style="width: 150px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($documents as $document)
                                        <tr>
                                            <td class="text-center">{{ $loop->iteration }}</td>
                                            <td>
                                                <strong>{{ $document->display_title }}</strong>
                                            </td>
                                            <td>
                                                @if($document->state === 'draft')
                                                    <span class="badge badge-warning">
                                                        <i class="fe fe-edit me-1"></i>Draft
                                                    </span>
                                                @else
                                                    <span class="badge badge-success">
                                                        <i class="fe fe-check-circle me-1"></i>Submitted
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    {{ $document->created_at->format('M d, Y') }}
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('minutes.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}"
                                                        class="btn btn-info text-white" title="View">
                                                        <i class="fe fe-eye"></i>
                                                    </a>
                                                    @if($document->canBeEdited())
                                                        <a href="{{ route('minutes.edit', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}"
                                                            class="btn btn-primary text-white" title="Edit">
                                                            <i class="fe fe-edit"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $documents->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fe fe-clipboard fs-1 text-muted"></i>
                            <p class="text-muted mb-3 mt-3">No weekly minutes found</p>
                            <a href="{{ route('minutes.create') }}" class="btn btn-primary">
                                <i class="fe fe-plus-circle me-1"></i>Create Your First Minutes
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

