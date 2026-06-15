<?php

namespace App\Http\Requests;

use App\Enums\TransferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTransferStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transfer = $this->route('transfer');

        return $transfer && $this->user()?->can('changeStatus', $transfer);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(TransferStatus::values())],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
