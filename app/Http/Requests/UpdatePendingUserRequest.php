<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Corrections an admin makes while reviewing a registration. The field set
 * mirrors {@see UpdateUserRequest} minus everything the account itself owns
 * (photo, documents, billing profile), which is not what a review is about.
 */
class UpdatePendingUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            // Only platform roles: a vendor's custom team role would grant this
            // account permissions scoped to somebody else's shop.
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->whereNull('owner_id')],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'cin' => ['nullable', 'string', 'max:50'],
            'ice_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'pickup_address_1' => ['nullable', 'string', 'max:1000'],
            'pickup_address_2' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
