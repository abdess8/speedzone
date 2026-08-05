<?php

namespace App\Http\Requests;

use App\Enums\ReturnStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeReturnStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $return = $this->route('return');

        return $return && ($this->user()?->can('changeStatus', $return) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(ReturnStatus::values())],
            'comment' => ['nullable', 'string', 'max:2000'],
            'current_location_city_id' => ['nullable', 'integer', 'exists:cities,id'],
            // The hand-back step cannot start without a carrier; every other
            // step ignores this field.
            'driver_id' => [
                Rule::requiredIf(fn () => $this->input('status') === ReturnStatus::IN_DELIVERY_TO_VENDOR->value),
                'nullable',
                'integer',
                'exists:users,id',
            ],
        ];
    }
}
