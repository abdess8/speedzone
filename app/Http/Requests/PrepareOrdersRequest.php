<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Checkbox selection on the preparation queue.
 *
 * Ids are not checked against the queue here: an order somebody else packed
 * between the page render and the click is a race, not a bad request, and the
 * service counts it as skipped instead of failing the whole trolley.
 */
class PrepareOrdersRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function ids(): array
    {
        return array_map('intval', $this->input('ids', []));
    }
}
