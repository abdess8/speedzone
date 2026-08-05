<?php

namespace App\Http\Requests;

use App\Enums\DeliveryOutcome;
use App\Enums\OrderFailureReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the driver reports at the door.
 *
 * Authorisation is left to the controller's `updateStatus` policy check, which
 * needs the resolved order; this class only guarantees a non-delivery always
 * arrives with a reason attached.
 */
class StoreDeliveryOutcomeRequest extends FormRequest
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
        return [
            'outcome' => ['required', 'string', Rule::in(DeliveryOutcome::values())],
            'failure_reason' => [
                Rule::requiredIf(fn () => $this->input('outcome') === DeliveryOutcome::FAILED->value),
                'nullable',
                'string',
                Rule::in(OrderFailureReason::values()),
            ],
            'note' => ['nullable', 'string', 'max:500'],
            'attachment' => [
                'nullable',
                'file',
                'max:5120',
                'mimes:jpg,jpeg,png,gif,webp,heic,pdf',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'failure_reason.required' => __('orders.failure.reason_required'),
        ];
    }

    public function outcome(): DeliveryOutcome
    {
        return DeliveryOutcome::from($this->string('outcome')->toString());
    }

    public function failureReason(): ?OrderFailureReason
    {
        return OrderFailureReason::tryFrom((string) $this->input('failure_reason'));
    }
}
