<x-app-layout>


                <div class="row align-items-center mb-3 border-bottom no-gutters">
                    <div class="col">
                        <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                                    aria-controls="home" aria-selected="true">Deposits</a>
                            </li>
                        </ul>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-sm" onclick="reloadPage()">
                            <i class="ti ti-16 fe-refresh-ccw text-muted"></i>
                        </button>
                        @can('approve pettycash payments')
                            <button type="button" class="btn mb-2 btn-primary btn-sm"
                                data-toggle="modal" data-target="#staticBackdrop"
                                onclick="openNewDepositModal()">
                                New deposit<span class="ti ti-plus fe-16 ml-2"></span>
                            </button>
                        @endcan
                    </div>
                </div>

                <div class="row my-2">
                   
                    <div class="col-md-12">
                        <div class="card shadow-none border">
                            <div class="card-header mb-1" style="display: flex;justify-content: space-between;">
                                <h4 class="h3 mb-3">
                                    @if ($remaining < 0)
                                        <div class="fs-5 bg-danger badge">Remaining Amount:
                                            <strong>{{ number_format($remaining, 0, '.', ',') }}/=</strong>
                                        </div>
                                    @else
                                        <div class="fs-5 bg-success badge">Remaining Amount:
                                            <strong>{{ number_format($remaining, 0, '.', ',') }}/=</strong>
                                        </div>
                                    @endif
                                </h4>
                            </div>
                            <div class="card-body">
                                <!-- table -->
                                <table class="table table-bordered datatables" id="dataTable-1">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>No.</th>
                                            <th>Deposit Amount</th>
                                            <th>Remaining Balance</th>
                                            <th>Date Deposited</th>
                                            <th>Deposited By</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($deposits as $index => $deposit)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ number_format($deposit->deposit, 0, '.', ',') }}/=</td>
                                                <td>
                                                    <span class="{{ $deposit->remaining < 0 ? 'text-danger' : '' }}">
                                                        {{ number_format($deposit->remaining, 0, '.', ',') }}/=
                                                    </span>
                                                </td>
                                                <td>{{ $deposit->created_at }}</td>
                                                <td>{{ $deposit->user->name }}</td>
                                                <td>
                                                    <button type="button" class="btn btn-secondary btn-sm"
                                                        data-toggle="modal" data-target="#depositDetailModal"
                                                        data-deposit="{{ number_format($deposit->deposit, 0, '.', ',') }}/="
                                                        data-remaining="{{ number_format($deposit->remaining, 0, '.', ',') }}/="
                                                        data-date="{{ $deposit->created_at }}"
                                                        data-by="{{ $deposit->user->name }}"
                                                        data-description="{{ $deposit->description ?? 'No description provided' }}"
                                                        onclick="openDepositModal('{{ number_format($deposit->deposit, 0, '.', ',') }}/=', '{{ number_format($deposit->remaining, 0, '.', ',') }}/=', '{{ $deposit->created_at }}', '{{ $deposit->user->name }}', '{{ $deposit->description ?? 'No description provided' }}')">
                                                        <i class="ti ti-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>


    <!-- New Deposit Modal -->
    <div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" aria-hidden="true"
        aria-labelledby="depositModalLabel" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="depositModalLabel">New Deposit</h5>
                </div>

                <form method="POST" action="{{ route('deposit.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ Auth::user()->id }}">
                    <input type="hidden" name="department_id" value="{{ Auth::user()->department_id }}">

                    <div class="modal-body">
                        <div class="form-group mb-3">
                            <label for="deposit">Amount of Money</label>
                            <input type="number" name="deposit" class="form-control" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="created_at">Date Deposited</label>
                            <input type="date" name="created_at" class="form-control" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="description">Description (optional)</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Deposit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Deposit Detail Modal -->
    <div class="modal fade" id="depositDetailModal" tabindex="-1" aria-labelledby="depositDetailModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Deposit Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered table-sm">
                        <tbody>
                            <tr>
                                <th scope="row">Amount Deposited</th>
                                <td class="text-secondary" id="modalDeposit"></td>
                            </tr>
                            <tr>
                                <th scope="row">Remaining Amount</th>
                                <td class="text-secondary" id="modalRemaining"></td>
                            </tr>
                            <tr>
                                <th scope="row">Date Deposited</th>
                                <td class="text-secondary" id="modalDate"></td>
                            </tr>
                            <tr>
                                <th scope="row">Description</th>
                                <td class="text-muted" id="modalDescription"></td>
                            </tr>
                        </tbody>
                    </table>
                    <p><span class="text-muted">Deposited By:</span> <strong id="modalBy"></strong></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Check if Bootstrap is loaded
            if (typeof bootstrap === 'undefined') {
                console.error('Bootstrap JS is not loaded');
                return;
            }

            // Initialize modals
            const depositModal = document.getElementById('depositDetailModal');
            const newDepositModal = document.getElementById('staticBackdrop');

            if (depositModal) {
                depositModal.addEventListener('show.bs.modal', event => {
                    const button = event.relatedTarget;

                    document.getElementById('modalDeposit').textContent = button.getAttribute('data-deposit');
                    document.getElementById('modalRemaining').textContent = button.getAttribute('data-remaining');
                    document.getElementById('modalDate').textContent = button.getAttribute('data-date');
                    document.getElementById('modalBy').textContent = button.getAttribute('data-by');
                    document.getElementById('modalDescription').textContent = button.getAttribute('data-description');
                });
            }

            // Alternative: Manual modal triggering for testing
            window.openDepositModal = function(deposit, remaining, date, by, description) {
                document.getElementById('modalDeposit').textContent = deposit;
                document.getElementById('modalRemaining').textContent = remaining;
                document.getElementById('modalDate').textContent = date;
                document.getElementById('modalBy').textContent = by;
                document.getElementById('modalDescription').textContent = description;

                const modal = new bootstrap.Modal(document.getElementById('depositDetailModal'));
                modal.show();
            };

            window.openNewDepositModal = function() {
                const modal = new bootstrap.Modal(document.getElementById('staticBackdrop'));
                modal.show();
            };
        });

        function reloadPage() {
            location.reload();
        }

        // jQuery fallback for older Bootstrap versions
        $(document).ready(function() {
            if (typeof $ !== 'undefined' && $.fn.modal) {
                // If using jQuery/Bootstrap 4
                $('#depositDetailModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);

                    $('#modalDeposit').text(button.data('deposit'));
                    $('#modalRemaining').text(button.data('remaining'));
                    $('#modalDate').text(button.data('date'));
                    $('#modalBy').text(button.data('by'));
                    $('#modalDescription').text(button.data('description'));
                });
            }
        });
    </script>
</x-app-layout>
