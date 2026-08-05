<?php

namespace App\Http\Requests;

use App\Enums\OrderFailureReason;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionOrderStatusRequest extends FormRequest
{
    /**
     * Authorization is handled by OrderPolicy::updateStatus() in the controller,
     * which needs the resolved Order instance to run its ownership check.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_status' => ['required', 'string', Rule::in(OrderStatus::values())],
            'comment' => ['nullable', 'string', 'max:1000'],
            'failure_reason' => [
                Rule::requiredIf(fn () => $this->input('to_status') === OrderStatus::FAILED->value),
                'nullable',
                'string',
                Rule::in(OrderFailureReason::values()),
            ],
            'failure_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'failure_reason.required' => __('orders.failure.reason_required'),
        ];
    }

    /**
     * Extra attributes persisted alongside the status change.
     *
     * @return array{failure_reason: string|null, failure_note: string|null}
     */
    public function transitionContext(): array
    {
        return [
            'failure_reason' => $this->input('failure_reason'),
            'failure_note' => $this->input('failure_note'),
        ];
    }
}
