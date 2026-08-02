<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && (
            $user->hasPermission('orders.read.all')
            || $user->hasPermission('orders.read.own')
            || $user->hasPermission('orders.read.assigned')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => [
                'sometimes',
                'string',
                Rule::in([
                    'today',
                    'yesterday',
                    'last_7_days',
                    'last_30_days',
                    'this_month',
                    'last_month',
                    'custom',
                ]),
            ],
            'from' => ['required_if:period,custom', 'nullable', 'date'],
            'to' => ['required_if:period,custom', 'nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
