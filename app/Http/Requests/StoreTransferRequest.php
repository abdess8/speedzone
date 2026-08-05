<?php

namespace App\Http\Requests;

use App\Enums\TransferContentType;
use App\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Transfer::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_city_id' => ['required', 'integer', 'exists:cities,id'],
            'to_city_id' => ['required', 'integer', 'exists:cities,id', 'different:from_city_id'],
            'content_type' => ['required', Rule::enum(TransferContentType::class)],
            // Which of the two pools is mandatory depends on the content type,
            // and a mixed manifest only needs one of them filled; the service
            // owns that rule so it stays true for the API as well.
            'order_ids' => ['nullable', 'array'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
            'return_ids' => ['nullable', 'array'],
            'return_ids.*' => ['integer', 'exists:returns,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function contentType(): TransferContentType
    {
        return TransferContentType::from($this->string('content_type')->toString());
    }
}
