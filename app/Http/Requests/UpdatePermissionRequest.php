<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionId = $this->route('permission')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($permissionId)],
            'resource' => ['required', 'string', 'max:100'],
            'action' => ['required', 'string', 'max:100'],
            'scope' => ['nullable', Rule::in(['own', 'all'])],
            'type' => ['required', Rule::in(['resource', 'workflow_transition', 'admin'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
