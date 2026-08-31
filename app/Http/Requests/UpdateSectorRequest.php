<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }

        // The payout is invisible to whoever cannot read it, so a submitted
        // value can only come from a hand-crafted request.
        if (! $this->user()?->hasPermission('sectors.read_driver_price')) {
            $this->request->remove('delivery_driver_price');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sector = $this->route('sector');
        $sectorId = $sector?->id;
        $cityId = $this->input('city_id', $sector?->city_id);

        return [
            'city_id' => ['sometimes', 'required', 'integer', Rule::exists('cities', 'id')->whereNull('deleted_at')],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('sectors', 'name')
                    ->where(fn ($query) => $query->where('city_id', $cityId))
                    ->ignore($sectorId)
                    ->whereNull('deleted_at'),
            ],
            'delivery_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'return_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'delivery_driver_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'delivery_delay' => ['sometimes', 'nullable', 'string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A sector with this name already exists in the selected city.',
        ];
    }
}
