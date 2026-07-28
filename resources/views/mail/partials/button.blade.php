@php
    $buttonUrl = $buttonUrl ?? null;
    $buttonLabel = $buttonLabel ?? 'Open';
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 8px 0 4px;">
    <tr>
        <td>
            <a href="{{ $buttonUrl }}" class="btn"
                style="display:inline-block;background-color:#18181b;color:#ffffff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:14px;font-weight:600;line-height:1;text-decoration:none;padding:12px 20px;border-radius:4px;">
                {{ $buttonLabel }}
            </a>
        </td>
    </tr>
</table>
