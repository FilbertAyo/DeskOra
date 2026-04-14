<x-app-layout>



     <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">
                       All Requests
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row my-2">

       
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-body">
                    <table class="table table-bordered datatables" id="dataTable-1">
                        <thead class="thead-light">
                            <tr>
                                <th>No.</th>
                                <th>Request By</th>
                                <th>Request for</th>
                                <th>Request Type</th>
                                <th>Amount</th>
                                <th>Date Requested</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                           @foreach ($requests as $index => $item)

                             @if (
                                                    ($item->is_transporter == true && $item->attachment == null) ||
                                                        ($item->request_for == 'Office Supplies' && $item->attachment == null))
                                        <tr class="alert alert-danger">
                                            <td>{{ $loop->iteration }}
                                            </td>
                                            <td> {{ $item->user->name }}</td>
                                            <td> {{ $item->request_for }}</td>
                                            <td>{{ $item->request_type }}</td>
                                            <td>{{ number_format($item->amount) }}</td>
                                            <td>{{ $item->created_at }}</td>

                                            <td>
                                                @if ($item->status == 'pending')
                                                    <span class="badge bg-danger text-white">{{ $item->status }}</span>
                                                @elseif($item->status == 'processing')
                                                    <span class="badge bg-warning">{{ $item->status }}</span>
                                                @elseif($item->status == 'rejected')
                                                    <span class="badge bg-secondary text-white">{{ $item->status }}</span>
                                                @elseif($item->status == 'resubmission')
                                                    <span class="badge btn-label-danger">{{ $item->status }}</span>
                                                @else
                                                    <span class="badge bg-success">{{ $item->status }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('petty.details', $item->id) }}"
                                                    class="btn btn-sm btn-secondary text-white">
                                                    <i class="ti ti-eye"></i></a>
                                            </td>
                                        </tr>
                                        @else

                                            <tr>
                                            <td>{{ $loop->iteration }}



                                            </td>
                                            <td> {{ $item->user->name }}</td>
                                            <td> {{ $item->request_for }}</td>
                                            <td>{{ $item->request_type }}</td>
                                            <td>{{ number_format($item->amount) }}</td>
                                            <td>{{ $item->created_at }}</td>

                                            <td>
                                                @if ($item->status == 'pending')
                                                    <span class="badge bg-danger text-white">{{ $item->status }}</span>
                                                @elseif($item->status == 'processing')
                                                    <span class="badge bg-warning ">{{ $item->status }}</span>
                                                @elseif($item->status == 'rejected')
                                                    <span class="badge bg-secondary text-white">{{ $item->status }}</span>
                                                @elseif($item->status == 'resubmission')
                                                    <span class="badge btn-label-danger">{{ $item->status }}</span>
                                                @else
                                                    <span class="badge bg-success">{{ $item->status }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('petty.details', $item->id) }}"
                                                    class="btn btn-sm btn-secondary text-white">
                                                    <i class="ti ti-eye"></i></a>
                                            </td>
                                        </tr>

                                        @endif
                                    @endforeach
                        </tbody>
                    </table>

                </div>
            </div>
        </div>


    </div>




</x-app-layout>
