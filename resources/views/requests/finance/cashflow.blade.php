<x-app-layout>


    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">Running Balance Report</a>
                </li>

            </ul>
        </div>
        <div class="col-auto">

            <a href="{{ route('cashflow.download', ['format' => 'pdf', 'filter_type' => request('filter_type')]) }}"
                class="btn mb-2 btn-danger btn-sm">
                Download PDF
            </a>
            <a href="{{ route('cashflow.download', ['format' => 'excel', 'filter_type' => request('filter_type')]) }}"
                class="btn mb-2 btn-primary btn-sm">
                Download Excel
            </a>
        </div>
    </div>

    <div class="mb-3 d-flex justify-content-between gap-2">
        <form method="GET" action="{{ route('cashflow.index') }}">

            <div class="col-md-12">
                <select name="filter_type" class="form-control" onchange="this.form.submit()">
                    <option value="">Show All</option>
                    <option value="daily" {{ request('filter_type') == 'daily' ? 'selected' : '' }}>Daily</option>
                    <option value="monthly" {{ request('filter_type') == 'monthly' ? 'selected' : '' }}>Monthly
                    </option>
                </select>
            </div>
        </form>
    </div>


    <div class="row my-2">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ $filterType ? 'Date' : 'Transaction Date' }}</th>

                                @unless ($isFiltered)
                                    <th>Name</th>
                                    <th>Deposit</th>
                                @endunless

                                <th>Deduction</th>
                                <th>Running Balance</th>

                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $tx)
                                <tr>
                                    <td>{{ $tx['label'] ?? \Carbon\Carbon::parse($tx['date'])->format('Y-m-d') }}</td>

                                    @unless ($isFiltered)
                                        <td>{{ $tx['requested_by'] }}</td>
                                        <td><strong>{{ $tx['deposit'] ? number_format($tx['deposit']) : '-' }}</strong></td>
                                    @endunless

                                    <td>{{ $tx['deduction'] ? number_format($tx['deduction']) : '-' }}</td>
                                    <td class="{{ $tx['remaining'] < 0 ? 'text-danger' : '' }}">
                                        {{ number_format($tx['remaining']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>



    <script>
        function toggleInputs() {
            const type = document.querySelector('[name="filter_type"]').value;
            document.getElementById('date-input').style.display = type === 'daily' ? 'block' : 'none';
            document.getElementById('month-input').style.display = type === 'monthly' ? 'block' : 'none';
        }
        document.addEventListener('DOMContentLoaded', toggleInputs);
    </script>

</x-app-layout>
