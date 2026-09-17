@props(['url'])
@php
    $company = config('company.name', 'SpeedZone Express');
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" target="_blank" rel="noopener" style="display: inline-block; text-decoration: none;">
<img src="cid:{{ \App\Support\MailBranding::LOGO_CID }}" class="logo" alt="{{ $company }}" width="220">
</a>
</td>
</tr>
<tr>
<td class="brand-bar" aria-hidden="true">&nbsp;</td>
</tr>
