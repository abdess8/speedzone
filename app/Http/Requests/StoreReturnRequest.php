<?php

namespace App\Http\Requests;

use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->canCreateReturnRequest() || $user->canCreateDriverReturn();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'reason' => ['required', 'string', Rule::in(ReturnReason::values())],
            'return_notes' => ['nullable', 'string', 'max:2000'],
            'initiated_by_role' => ['nullable', 'string', Rule::in(ReturnInitiatedByRole::values())],
            'current_location_city_id' => ['nullable', 'integer', 'exists:cities,id'],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('returns.errors.create_forbidden'));
    }
}
