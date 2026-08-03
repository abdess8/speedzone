<?php

namespace App\Http\Requests;

use App\Support\DashboardPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    /**
     * Two conditions, because they answer two different questions: `dashboard.view`
     * says the screen is open to this actor, and the order-read scope says which
     * rows it may aggregate. A role holding one without the other has nothing to
     * show, so it is turned away rather than served an empty dashboard.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || ! $user->hasPermission(DashboardPermissions::VIEW)) {
            return false;
        }

        return $user->hasPermission('orders.read.all')
            || $user->hasPermission('orders.read.own')
            || $user->hasPermission('orders.read.assigned');
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
