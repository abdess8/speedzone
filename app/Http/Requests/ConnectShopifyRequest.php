<?php

namespace App\Http\Requests;

use App\Enums\EcommercePlatform;
use App\Models\EcommerceIntegration;
use App\Models\Store;
use App\Support\EcommerceIntegrationPermissions;
use App\Support\ShopifyShopDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConnectShopifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! EcommerceIntegrationPermissions::canConnect($user, EcommercePlatform::Shopify)) {
            return false;
        }

        $store = $this->store();

        if ($store === null) {
            return true;
        }

        return $user->can('create', [EcommerceIntegration::class, EcommercePlatform::Shopify, $store]);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('shop_slug')) {
            $this->merge([
                'shop_slug' => ShopifyShopDomain::normalize($this->input('shop_slug')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'shop_slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.myshopify\.com$/'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shop_slug.regex' => __('integrations.shopify.validation.shop_slug'),
            'shop_slug.required' => __('integrations.shopify.validation.shop_slug'),
            'access_token.required' => __('integrations.shopify.validation.access_token'),
            'client_id.required' => __('integrations.shopify.validation.client_id'),
            'client_secret.required' => __('integrations.shopify.validation.client_secret'),
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
            ->where('platform', EcommercePlatform::Shopify->value)
            ->first();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $existing = $this->existing();
            $hasClientId = filled($this->input('client_id')) || filled($existing?->client_id);
            $hasClientSecret = filled($this->input('client_secret')) || ($existing?->hasClientSecret() ?? false);
            $hasToken = filled($this->input('access_token')) || ($existing?->hasAccessToken() ?? false);

            if (($hasClientId && $hasClientSecret) || $hasToken) {
                return;
            }

            if ($hasClientId && ! $hasClientSecret) {
                $validator->errors()->add('client_secret', __('integrations.shopify.validation.client_secret'));

                return;
            }

            $validator->errors()->add('client_id', __('integrations.shopify.validation.credentials'));
        });
    }
}
