<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
            'resource' => ['required', 'string', 'max:100'],
            'action' => ['required', 'string', 'max:100'],
            'scope' => ['nullable', Rule::in(['own', 'all'])],
            'type' => ['required', Rule::in(['resource', 'workflow_transition', 'admin'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
