@props(['url'])
<tr>
<td class="header" style="padding:28px 32px 20px;text-align:left;border-bottom:1px solid #f4f4f5;background-color:#ffffff;">
<a href="{{ $url }}" style="display:inline-block;color:#18181b;font-size:13px;font-weight:600;letter-spacing:0.08em;text-decoration:none;text-transform:uppercase;">
@if (trim($slot) === 'Laravel')
{{ config('app.name', 'WorkHub') }}
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
