<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PartnerOrderBulkAssignDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && ($user->hasPermission('driver_invoices.assign_driver')
                || $user->hasPermission('partners.deliveries.manage'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'driver_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
