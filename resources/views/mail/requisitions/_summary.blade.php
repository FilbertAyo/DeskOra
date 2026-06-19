<table style="width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px;">
    <tr>
        <td style="padding: 6px 0; color: #666;">Reference</td>
        <td style="padding: 6px 0;"><strong>{{ $ref }}</strong></td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #666;">Requester</td>
        <td style="padding: 6px 0;"><strong>{{ $requesterName }}</strong></td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #666;">Activity</td>
        <td style="padding: 6px 0;"><strong>{{ $summary }}</strong></td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #666;">Amount</td>
        <td style="padding: 6px 0;"><strong>{{ $amountFormatted }}</strong></td>
    </tr>
    @if (!empty($departmentName))
        <tr>
            <td style="padding: 6px 0; color: #666;">Department</td>
            <td style="padding: 6px 0;"><strong>{{ $departmentName }}</strong></td>
        </tr>
    @endif
</table>
