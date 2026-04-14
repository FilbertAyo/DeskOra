<x-app-layout>

<div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Reports</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)">Reports</a></li>
                               
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="reloadPage()"
                                title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                       
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @can('view cashflow movements')
            <div class="col-sm-6 col-md-3">
                <a href="{{ route('reports.petties') }}" class="text-decoration-none">
                    <div class="card card-stats card-light card-round shadow-none border">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-stats">
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
                         
                            <div class="col-12 col-stats">
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
                      
                            <div class="col-12 col-stats">
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
