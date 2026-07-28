<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body style="background-color:#f4f4f5;margin:0;padding:0;">

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f4f4f5;">
<tr>
<td align="center" style="padding:32px 16px;">
<table class="inner-body" align="center" width="560" cellpadding="0" cellspacing="0" role="presentation" style="width:560px;max-width:560px;background-color:#ffffff;border:1px solid #e4e4e7;">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="content-cell" style="padding:28px 32px;">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
