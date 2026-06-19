<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 9pt;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
            font-size: 9pt;
        }

        h2,
        h4 {
            text-align: center;
            margin: 0;
            font-size: 9pt;
        }
    </style>
</head>

<body>

    <h2>MARS COMMUNICATION</h2>
    <h4>REQUISITION RECORDS REPORT</h4>
    <h4>{{ $department }} DEPARTMENT</h4>

    <table>
        <thead>
            <tr>
                @if ($isFiltered)
                    <th>Period</th>
                    <th>Requisitions</th>
                    <th>Amount</th>
                    <th>Cumulative Total</th>
                @else
                    <th>Date Authorized</th>
                    <th>Reference</th>
                    <th>Requester</th>
                    <th>Activity</th>
                    <th>Project</th>
                    <th>Budget Code</th>
                    <th>Amount</th>
                    <th>Cumulative Total</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($transactions as $tx)
                <tr>
                    @if ($isFiltered)
                        <td>{{ $tx['label'] }}</td>
                        <td>{{ $tx['count'] }}</td>
                        <td>{{ number_format($tx['amount'], 2) }}</td>
                        <td>{{ number_format($tx['cumulative'], 2) }}</td>
                    @else
                        <td>{{ \Carbon\Carbon::parse($tx['date'])->format('Y-m-d') }}</td>
                        <td>{{ $tx['reference'] }}</td>
                        <td>{{ $tx['requester'] }}</td>
                        <td>{{ $tx['activity'] }}</td>
                        <td>{{ $tx['project'] }}</td>
                        <td>{{ $tx['budget_code'] }}</td>
                        <td>{{ number_format($tx['amount'], 2) }}</td>
                        <td>{{ number_format($tx['cumulative'], 2) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

</body>

</html>
