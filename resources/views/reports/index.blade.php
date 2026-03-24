<x-app-layout>

    <!-- Header with Tabs + Buttons -->
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="reports-tab" data-toggle="tab" href="#reports" role="tab"
                        aria-controls="reports" aria-selected="true">Reports</a>
                </li>
            </ul>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-sm" onclick="reloadPage()">
                <i class="ti ti-16 fe-refresh-ccw text-muted"></i>
            </button>
        </div>
    </div>

    <!-- Reports Grid -->
    <div class="row my-2">

        @can('view cashflow movements')
            <div class="col-sm-6 col-md-3">
                <a href="{{ route('reports.petties') }}" class="text-decoration-none">
                    <div class="card card-stats card-light card-round shadow-none border">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-3">
                                    <div class="icon-big text-center">
                                        <i class="ti ti-16 fe-credit-card"></i>
                                    </div>
                                </div>
                                <div class="col-9 col-stats">
                                    <div class="numbers">
                                        <div class="h5">Petty Cash</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endcan

        <div class="col-sm-6 col-md-3">
            <a href="{{ route('reports.users') }}" class="text-decoration-none">
                <div class="card card-stats card-light card-round shadow-none border">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-3">
                                <div class="icon-big text-center">
                                    <i class="ti ti-16 fe-users"></i>
                                </div>
                            </div>
                            <div class="col-9 col-stats">
                                <div class="numbers">
                                    <div class="h5">Users List</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-sm-6 col-md-3">
            <a href="{{ route('reports.transaction') }}" class="text-decoration-none">
                <div class="card card-stats card-light card-round shadow-none border">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-3">
                                <div class="icon-big text-center">
                                    <i class="ti ti-16 fe-dollar-sign"></i>
                                </div>
                            </div>
                            <div class="col-9 col-stats">
                                <div class="numbers">
                                    <div class="h5">Transactions</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

    

    </div>

</x-app-layout>
