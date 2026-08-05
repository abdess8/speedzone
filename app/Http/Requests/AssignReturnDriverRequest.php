<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignReturnDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        $return = $this->route('return');

        return $return && ($this->user()?->can('assignDriver', $return) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', 'exists:users,id'],
            'dispatch' => ['sometimes', 'boolean'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
