<?php

namespace App\Http\Requests;

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
            'import_status' => ['required', 'string', Rule::in(YouCanImportStatus::values())],
            'field_mapping' => ['nullable', 'array'],
            'field_mapping.*' => ['nullable', 'string', 'max:191'],
        ];
    }
}
