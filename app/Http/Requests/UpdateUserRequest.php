<?php

namespace App\Http\Requests;

use App\Enums\BillingFrequency;
use App\Enums\SellerPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:1000'],
            'pickup_address_1' => ['nullable', 'string', 'max:1000'],
            'pickup_address_2' => ['nullable', 'string', 'max:1000'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'cin' => ['nullable', 'string', 'max:50'],
            'ice_number' => ['nullable', 'string', 'max:50'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'attached_files' => ['nullable', 'array'],
            'attached_files.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:5120'],
            'removed_files' => ['nullable', 'array'],
            'removed_files.*' => ['string'],

            // Seller billing profile.
            'billing_enabled' => ['nullable', 'boolean'],
            'billing_frequency' => ['nullable', Rule::in(BillingFrequency::values())],
            'next_billing_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', Rule::in(SellerPaymentMethod::values())],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'rib' => ['nullable', 'string', 'max:64'],
            'rib_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'cin_front_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'cin_back_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
