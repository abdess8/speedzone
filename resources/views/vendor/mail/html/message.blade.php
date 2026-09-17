<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('company.name', 'SpeedZone Express') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
<p class="footer-brand">{{ config('company.name', 'SpeedZone Express') }}</p>
<p class="footer-tagline">{{ __('mail.tagline') }}</p>
<p class="footer-contact">
{{ config('company.address') }}{{ config('company.city') ? ', '.config('company.city') : '' }}<br>
@if (config('company.phone'))
<a href="tel:{{ config('company.phone_link') }}">{{ config('company.phone') }}</a>
 ·
@endif
@if (config('company.email'))
<a href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a>
@endif
</p>
<p class="footer-copy">© {{ date('Y') }} {{ config('company.name', 'SpeedZone Express') }}. {{ __('mail.rights') }}</p>
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
