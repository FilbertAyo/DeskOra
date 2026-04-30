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
    <h4>RUNNING BALANCE REPORT</h4>
    <h4>{{ $department }} DEPARTMENT</h4>

    <table>
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
                    <td>{{ $tx['requested_by'] ?? '-' }}</td>
                    <td>{{ $tx['deposit'] ? number_format($tx['deposit']) : '-' }}</td>
                    @endunless
                    <td>{{ $tx['deduction'] ? number_format($tx['deduction']) : '-' }}</td>
                    <td>{{ number_format($tx['remaining']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>

</html>
