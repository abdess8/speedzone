<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Enums\PartnerAuthType;
use App\Enums\PartnerOrderField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerRequest extends FormRequest
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

        if ($this->has('sync_status')) {
            $this->merge(['sync_status' => $this->boolean('sync_status')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->sharedRules(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('partners', 'name')],
            'sync_frequency_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedRules(): array
    {
        return [
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:2048'],
            'ice_number' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'sync_status' => ['boolean'],
            'reception_city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'api_base_url' => ['nullable', 'url', 'max:2048'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'auth_type' => ['nullable', 'string', Rule::in(PartnerAuthType::values())],
            'endpoint_statuses' => ['nullable', 'string', 'max:255'],
            'endpoint_deliveries' => ['nullable', 'string', 'max:255'],
            'endpoint_update' => ['nullable', 'string', 'max:255'],
            'endpoint_login' => ['nullable', 'string', 'max:2048'],
            'api_key_header' => ['nullable', 'string', 'max:100'],
            'login_username_field' => ['nullable', 'string', 'max:50'],
            'login_password_field' => ['nullable', 'string', 'max:50'],
            'login_token_field' => ['nullable', 'string', 'max:100'],
            'ingestion_partner_status' => ['nullable', 'string', 'max:100'],
            'city_ids' => ['array'],
            'city_ids.*' => ['integer', Rule::exists('cities', 'id')],
            'sector_ids' => ['array'],
            'sector_ids.*' => ['integer', Rule::exists('sectors', 'id')],
            'status_mappings' => ['array'],
            'status_mappings.*.speedzone_status' => ['required', 'string', Rule::in(OrderStatus::values())],
            'status_mappings.*.partner_status' => ['required', 'string', 'max:100'],
            'field_mappings' => ['array'],
            'field_mappings.*.speedzone_field' => ['required', 'string', Rule::in(PartnerOrderField::values())],
            'field_mappings.*.partner_field' => ['required', 'string', 'max:100'],
        ];
    }
}
