<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignPartnerUsersRequest extends FormRequest
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
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'replace' => ['boolean'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function userIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->input('user_ids', []))));
    }
}
