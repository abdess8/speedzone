<?php

namespace App\Http\Requests;

use App\Enums\EcommercePlatform;
use App\Enums\OrderStatus;
use App\Enums\ShopifyExportStatus;
use App\Enums\ShopifyImportStatus;
use App\Enums\YouCanExportStatus;
use App\Enums\YouCanImportStatus;
use App\Models\EcommerceIntegration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateYouCanSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $integration = $this->route('integration');

        return $integration instanceof EcommerceIntegration
            && $this->user()?->can('update', $integration);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'auto_sync_enabled' => ['required', 'boolean'],
            'sync_interval_minutes' => ['required', 'integer', Rule::in(EcommerceIntegration::SYNC_INTERVALS)],
            'import_status' => ['required', 'string', Rule::in($this->allowedImportStatuses())],
            'field_mapping' => ['nullable', 'array'],
            'field_mapping.*' => ['nullable', 'string', 'max:191'],
            'status_mapping' => ['nullable', 'array'],
            'status_mapping.*' => ['nullable', 'string', Rule::in(['', ...$this->allowedExportStatuses()])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $mapping = $this->input('status_mapping');

            if (! is_array($mapping)) {
                return;
            }

            $allowed = OrderStatus::values();

            foreach (array_keys($mapping) as $status) {
                if (! in_array($status, $allowed, true)) {
                    $validator->errors()->add(
                        'status_mapping',
                        __('integrations.'.$this->platformKey().'.validation.status_mapping')
                    );

                    return;
                }
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function allowedExportStatuses(): array
    {
        $integration = $this->route('integration');

        if ($integration instanceof EcommerceIntegration && $integration->platform === EcommercePlatform::YouCan) {
            return YouCanExportStatus::values();
        }

        return ShopifyExportStatus::values();
    }

    private function platformKey(): string
    {
        $integration = $this->route('integration');

        return $integration instanceof EcommerceIntegration
            && $integration->platform === EcommercePlatform::YouCan
            ? 'youcan'
            : 'shopify';
    }

    /**
     * @return array<int, string>
     */
    private function allowedImportStatuses(): array
    {
        $integration = $this->route('integration');

        if ($integration instanceof EcommerceIntegration && $integration->platform === EcommercePlatform::Shopify) {
            return ShopifyImportStatus::values();
        }

        return YouCanImportStatus::values();
    }
}
