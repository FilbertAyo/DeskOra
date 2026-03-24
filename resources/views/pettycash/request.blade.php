<x-app-layout>


    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">My Requests</a>
                </li>

            </ul>
        </div>
        <div class="col-auto">
            @can('request pettycash')
                <a href="{{ route('petty.create') }}" class="btn mb-2 btn-primary btn-sm">
                    <span class="btn-label">
                        <i class="ti ti-plus-circle"></i>
                    </span>
                    New Request
                </a>
            @endcan
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
                                                <a href="{{ route('petty.show', \Vinkla\Hashids\Facades\Hashids::encode($item->id)) }}"
                                                    class="btn btn-sm btn-secondary text-white">
                                                    <i class="ti ti-eye"></i></a>
                                            </td>
                                        </tr>
                                        @else

                                            <tr>
                                            <td>{{ $loop->iteration }}

                                            </td>
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
                                                <a href="{{ route('petty.show', \Vinkla\Hashids\Facades\Hashids::encode($item->id)) }}"
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

    <script>
        function showEditBlockedNotice() {
            // Create a custom alert modal
            const alertHtml = `
                <div class="alert alert-danger alert-dismissible fade show position-fixed"
                     role="alert"
                     style="top: 20px; right: 20px; z-index: 9999; min-width: 350px; box-shadow: 0 4px 6px rgba(0,0,0,0.2);">
                    <div class="d-flex align-items-center">
                        <i class="ti ti-alert-circle fs-3 me-3"></i>
                        <div>
                            <strong class="d-block">Edit Blocked!</strong>
                            <span>You cannot edit this request because it's already being processed.</span>
                        </div>
                        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>
            `;

            // Append to body
            $('body').append(alertHtml);

            // Auto remove after 4 seconds
            setTimeout(function() {
                $('.alert-danger').fadeOut(400, function() {
                    $(this).remove();
                });
            }, 4000);
        }
    </script>

</x-app-layout>
