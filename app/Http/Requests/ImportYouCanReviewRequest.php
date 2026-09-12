<?php

namespace App\Http\Requests;

class ImportYouCanReviewRequest extends ImportOrdersRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (array_keys((array) $this->input('orders', [])) as $index) {
            $rules["orders.{$index}.id"] = ['required', 'integer'];
        }

        return $rules;
    }
}
