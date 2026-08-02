<x-mail::message>
# {{ __('seller_registration.emails.rejected_heading') }}

{{ __('seller_registration.emails.rejected_body', ['name' => $user->first_name ?: $user->name]) }}

@if ($reason)
**{{ __('seller_registration.emails.rejected_reason_label') }}**

{{ $reason }}
@endif

{{ __('seller_registration.emails.rejected_footer') }}

{{ config('app.name') }}
</x-mail::message>
