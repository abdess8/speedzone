<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create and update payload for a vendor team member.
 *
 * The store and role rules are scoped to the vendor's own rows, so a tampered
 * payload cannot attach a member to another account's shop or role. TeamService
 * filters them a second time before writing.
 */
class TeamMemberRequest extends FormRequest
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
        $member = $this->route('member');
        $ownerId = $this->user()->accountOwnerId();
        $isCreate = $member === null;

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($member?->id),
            ],
            'phone_number' => ['nullable', 'string', 'max:50'],
            // On update an empty password means "leave it unchanged".
            'password' => [
                $isCreate ? 'required' : 'nullable',
                'confirmed',
                Password::defaults(),
            ],
            'locale' => ['nullable', 'string', 'in:fr,en'],
            'store_ids' => ['required', 'array', 'min:1'],
            'store_ids.*' => [
                'integer',
                Rule::exists('stores', 'id')
                    ->where(fn ($query) => $query->where('owner_id', $ownerId))
                    ->whereNull('deleted_at'),
            ],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')
                    ->where(fn ($query) => $query->where('owner_id', $ownerId)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_ids.required' => __('team.errors.store_required'),
            'role_ids.required' => __('team.errors.role_required'),
            'role_ids.*.exists' => __('team.errors.role_required'),
        ];
    }
}
