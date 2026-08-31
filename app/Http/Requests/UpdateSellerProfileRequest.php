<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSellerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSeller();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone_number' => ['nullable', 'string', 'max:50'],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'address' => ['nullable', 'string', 'max:1000'],
            'pickup_address_1' => ['nullable', 'string', 'max:1000'],
            'pickup_address_2' => ['nullable', 'string', 'max:1000'],
            'cin' => ['nullable', 'string', 'max:50'],
            'ice_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'rib' => ['nullable', 'string', 'max:64'],
            'rib_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'cin_front_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'cin_back_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone_number' => __('profile.completion.fields.phone_number'),
            'city_id' => __('profile.completion.fields.city_id'),
            'address' => __('profile.completion.fields.address'),
            'pickup_address_1' => __('profile.completion.fields.pickup_address_1'),
            'pickup_address_2' => __('profile.completion.fields.pickup_address_2'),
            'cin' => __('profile.completion.fields.cin'),
            'ice_number' => __('profile.completion.fields.ice_number'),
            'bank_name' => __('profile.completion.fields.bank_name'),
            'rib' => __('profile.completion.fields.rib'),
            'rib_attachment' => __('profile.completion.fields.rib_attachment'),
            'cin_front_attachment' => __('profile.completion.fields.cin_front_attachment'),
            'cin_back_attachment' => __('profile.completion.fields.cin_back_attachment'),
        ];
    }
}
