<?php

namespace App\Http\Requests;

use App\Enums\EcommercePlatform;
use App\Models\EcommerceIntegration;
use App\Models\Store;
use App\Support\EcommerceIntegrationPermissions;
use App\Support\YouCanShopSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConnectYouCanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! EcommerceIntegrationPermissions::canConnect($user, EcommercePlatform::YouCan)) {
            return false;
        }

        $store = $this->store();

        if ($store === null) {
            return true;
        }

        return $user->can('create', [EcommerceIntegration::class, EcommercePlatform::YouCan, $store]);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('shop_slug')) {
            $this->merge([
                'shop_slug' => YouCanShopSlug::normalize($this->input('shop_slug')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $updating = $this->existing()?->hasPassword() === true;

        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'shop_slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [$updating ? 'nullable' : 'required', 'string', 'max:2000'],
            'two_factor_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shop_slug.regex' => __('integrations.youcan.validation.shop_slug'),
            'password.required' => __('integrations.youcan.validation.password'),
        ];
    }

    public function store(): ?Store
    {
        $id = $this->integer('store_id');

        if ($id < 1) {
            return null;
        }

        return Store::query()->find($id);
    }

    public function existing(): ?EcommerceIntegration
    {
        $store = $this->store();

        if ($store === null) {
            return null;
        }

        return EcommerceIntegration::query()
            ->where('store_id', $store->id)
            ->where('platform', EcommercePlatform::YouCan->value)
            ->first();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $existing = $this->existing();

            if ($existing !== null && blank($this->input('password')) && ! $existing->hasPassword()) {
                $validator->errors()->add('password', __('integrations.youcan.validation.password'));
            }
        });
    }
}
