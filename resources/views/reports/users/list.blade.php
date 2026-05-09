<x-app-layout>

    <!-- Header with Tabs + Buttons -->
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="users-tab" data-toggle="tab" href="#users" role="tab"
                        aria-controls="users" aria-selected="true">User Reports</a>
                </li>
            </ul>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-sm" onclick="reloadPage()">
                <i class="ti ti-16 fe-refresh-ccw text-muted"></i>
            </button>
        </div>
    </div>

    <!-- Filters Form -->
    <div class="row my-2">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-body">
                    <form action="{{ route('reports.users') }}" method="GET" class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label>From Date</label>
                            <input type="date" name="from" value="{{ request('from') }}" class="form-control">
                        </div>
                        <div class="col-md-5">
                            <label>To Date</label>
                            <input type="date" name="to" value="{{ request('to') }}" class="form-control">
                        </div>
                        <div class="col-md-2 align-self-end">
                            <button class="btn btn-primary btn-sm w-100">Filter</button>
                        </div>
                    </form>

                    <!-- Download Buttons -->
                    <div class="mb-3">
                        <a href="{{ route('reports.users.download', ['type' => 'pdf'] + request()->all()) }}"
                            class="btn btn-danger btn-sm me-1"><i class="bi bi-file-earmark-pdf-fill me-2"></i>PDF</a>
                        <a href="{{ route('reports.users.download', ['type' => 'excel'] + request()->all()) }}"
                            class="btn btn-success btn-sm me-1"><i class="bi bi-file-earmark-excel-fill me-2"></i>Excel</a>
                        <a href="{{ route('reports.users.download', ['type' => 'csv'] + request()->all()) }}"
                            class="btn btn-info btn-sm">CSV</a>
                    </div>

                    <!-- Users Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone Number</th>
                                    <th>Department Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $user)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->email }}</td>
                                        <td>{{ $user->phone }}</td>
                                        <td>{{ $user->department->name ?? 'N/A'}}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No users found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>

</x-app-layout>
