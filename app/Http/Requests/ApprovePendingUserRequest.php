<?php

namespace App\Http\Requests;

use App\Models\Permission;
use App\Support\SellerRegistrationPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovePendingUserRequest extends FormRequest
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
        $allowedIds = Permission::query()
            ->whereIn('name', SellerRegistrationPermissions::assignable())
            ->pluck('id')
            ->all();

        return [
            // Optional: the review screen no longer picks permissions one by
            // one, and the controller falls back to the seller defaults.
            'permission_ids' => ['nullable', 'array', 'min:1'],
            'permission_ids.*' => ['integer', Rule::in($allowedIds)],
        ];
    }
}
