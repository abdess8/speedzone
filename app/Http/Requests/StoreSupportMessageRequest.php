<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSupportMessageRequest extends FormRequest
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
            'message' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,gif,doc,docx,xls,xlsx,csv,txt', 'max:10240'],
        ];
    }

    /**
     * Require at least a message body or an attachment.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (blank($this->input('message')) && ! $this->hasFile('attachment')) {
                $validator->errors()->add('message', __('support_tickets.errors.empty_message'));
            }
        });
    }
}
