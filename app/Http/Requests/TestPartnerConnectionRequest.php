<?php

namespace App\Http\Requests;

use App\Enums\PartnerAuthType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestPartnerConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('partners.update')
            || $this->user()?->hasPermission('partners.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'api_base_url' => ['nullable', 'url', 'max:2048'],
            'auth_type' => ['nullable', 'string', Rule::in(PartnerAuthType::values())],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'endpoint_statuses' => ['nullable', 'string', 'max:255'],
            'endpoint_login' => ['nullable', 'string', 'max:2048'],
            'api_key_header' => ['nullable', 'string', 'max:100'],
            'login_username_field' => ['nullable', 'string', 'max:50'],
            'login_password_field' => ['nullable', 'string', 'max:50'],
            'login_token_field' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $authType = PartnerAuthType::resolve($this->input('auth_type'));
            $hasBase = filled($this->input('api_base_url'));
            $hasLogin = filled($this->input('endpoint_login'));

            if (! $hasBase && ! $hasLogin) {
                $validator->errors()->add('api_base_url', 'API base URL or login endpoint is required.');
            }

            if ($authType === PartnerAuthType::LOGIN_TOKEN && ! $hasLogin) {
                $validator->errors()->add('endpoint_login', 'Login endpoint is required for LOGIN_TOKEN authentication.');
            }
        });
    }
}
