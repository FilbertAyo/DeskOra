<table role="presentation" class="meta-table" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;margin:4px 0 20px;">
    <tr>
        <td class="meta-label" style="width:34%;padding:10px 12px 10px 0;border-bottom:1px solid #f4f4f5;color:#71717a;font-size:14px;">Reference</td>
        <td class="meta-value" style="padding:10px 0;border-bottom:1px solid #f4f4f5;color:#18181b;font-size:14px;font-weight:600;">{{ $ref }}</td>
    </tr>
    <tr>
        <td class="meta-label" style="width:34%;padding:10px 12px 10px 0;border-bottom:1px solid #f4f4f5;color:#71717a;font-size:14px;">Requester</td>
        <td class="meta-value" style="padding:10px 0;border-bottom:1px solid #f4f4f5;color:#18181b;font-size:14px;font-weight:600;">{{ $requesterName }}</td>
    </tr>
    <tr>
        <td class="meta-label" style="width:34%;padding:10px 12px 10px 0;border-bottom:1px solid #f4f4f5;color:#71717a;font-size:14px;">Activity</td>
        <td class="meta-value" style="padding:10px 0;border-bottom:1px solid #f4f4f5;color:#18181b;font-size:14px;font-weight:600;">{{ $summary }}</td>
    </tr>
    <tr>
        <td class="meta-label" style="width:34%;padding:10px 12px 10px 0;border-bottom:1px solid #f4f4f5;color:#71717a;font-size:14px;">Amount</td>
        <td class="meta-value" style="padding:10px 0;border-bottom:1px solid #f4f4f5;color:#18181b;font-size:14px;font-weight:600;">{{ $amountFormatted }}</td>
    </tr>
    @if (! empty($departmentName))
        <tr>
            <td class="meta-label" style="width:34%;padding:10px 12px 10px 0;border-bottom:1px solid #f4f4f5;color:#71717a;font-size:14px;">Department</td>
            <td class="meta-value" style="padding:10px 0;border-bottom:1px solid #f4f4f5;color:#18181b;font-size:14px;font-weight:600;">{{ $departmentName }}</td>
        </tr>
    @endif
</table>
