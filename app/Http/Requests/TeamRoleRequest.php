<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create and update payload for a vendor-defined role.
 *
 * Permission names are only checked for existence here; the ceiling is applied
 * in TeamRoleService, which is the single place that decides what a vendor may
 * delegate.
 */
class TeamRoleRequest extends FormRequest
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
        /** @var Role|null $role */
        $role = $this->route('role');
        $ownerId = $this->user()->accountOwnerId();

        return [
            'label' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'label')
                    ->where(fn ($query) => $query->where('owner_id', $ownerId))
                    ->ignore($role?->id),
            ],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.required' => __('team.roles.errors.permission_required'),
        ];
    }
}
