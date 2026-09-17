<x-mail::layout>
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ config('company.name', 'SpeedZone Express') }}
        </x-mail::header>
    </x-slot:header>

    {{ $slot }}

    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    <x-slot:footer>
        <x-mail::footer>
{{ config('company.name', 'SpeedZone Express') }} — {{ __('mail.tagline') }}

{{ config('company.address') }}{{ config('company.city') ? ', '.config('company.city') : '' }}
{{ config('company.phone') }} · {{ config('company.email') }}

© {{ date('Y') }} {{ config('company.name', 'SpeedZone Express') }}. {{ __('mail.rights') }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
