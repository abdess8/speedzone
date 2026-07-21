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
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['integer', Rule::in($allowedIds)],
        ];
    }
}
