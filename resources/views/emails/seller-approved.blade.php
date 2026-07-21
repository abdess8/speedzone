<x-mail::message>
# {{ __('seller_registration.emails.approved_heading') }}

{{ __('seller_registration.emails.approved_body', ['name' => $user->first_name ?: $user->name]) }}

<x-mail::button :url="$loginUrl">
{{ __('seller_registration.emails.approved_button') }}
</x-mail::button>

{{ __('seller_registration.emails.approved_footer') }}

{{ config('app.name') }}
</x-mail::message>
