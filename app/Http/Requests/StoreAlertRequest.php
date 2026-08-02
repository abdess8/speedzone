<?php

namespace App\Http\Requests;

use App\Enums\AlertFormat;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\City;
use App\Models\Role;
use App\Support\AlertHtml;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is in the controller/policy
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // The message is rendered as HTML on every recipient's screen, so
            // it is cleaned before validation rather than on the way out: what
            // gets stored is what was vetted.
            'message' => AlertHtml::sanitize($this->input('message')),
            'is_active' => $this->boolean('is_active'),
            'is_dismissible' => $this->boolean('is_dismissible'),
            'target_roles' => $this->normaliseTargets('target_roles'),
            'target_cities' => $this->normaliseTargets('target_cities', numeric: true),
            'target_user_ids' => $this->normaliseTargets('target_user_ids', numeric: true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:200000'],
            'type' => ['required', Rule::enum(AlertType::class)],
            'display_format' => ['required', Rule::enum(AlertFormat::class)],
            'is_dismissible' => ['boolean'],
            'is_active' => ['boolean'],

            'target_roles' => ['array'],
            'target_roles.*' => [Rule::in($this->offeredRoles())],
            'target_cities' => ['array'],
            'target_cities.*' => [Rule::in($this->offeredCities())],
            'target_user_ids' => ['array'],
            'target_user_ids.*' => ['integer', Rule::exists('users', 'id')],

            'end_date' => ['required', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after' => __('alerts.validation.end_date_future'),
            'target_roles.*.in' => __('alerts.validation.unknown_role'),
            'target_cities.*.in' => __('alerts.validation.unknown_city'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Roles and cities narrow each other, so leaving either one empty
            // silences the whole broadcast. That is legitimate when specific
            // people are named, and a mistake otherwise.
            $broadcasts = filled($this->input('target_roles')) && filled($this->input('target_cities'));

            if (! $broadcasts && blank($this->input('target_user_ids'))) {
                $validator->errors()->add('target_roles', __('alerts.validation.no_audience'));
            }
        });
    }

    /**
     * Drops empty entries and collapses the everyone marker, which the form
     * sends alongside the individual boxes it ticks.
     *
     * Identifiers are stored as numbers rather than as the strings a form
     * posts, so the stored JSON can be compared against real ids later on.
     *
     * @return array<int, string|int>
     */
    private function normaliseTargets(string $key, bool $numeric = false): array
    {
        $values = array_values(array_filter(
            (array) $this->input($key, []),
            fn ($value) => $value !== null && $value !== ''
        ));

        if (in_array(Alert::EVERYONE, array_map('strval', $values), true)) {
            return [Alert::EVERYONE];
        }

        return array_map(
            fn ($value) => $numeric && ctype_digit((string) $value) ? (int) $value : (string) $value,
            $values
        );
    }

    /**
     * An audience is only ever picked from what the form offered, so anything
     * else is a stale or tampered value: a city that no longer exists would
     * quietly match nobody, which looks exactly like a broken announcement.
     *
     * The everyone marker travels in the same array as real identifiers, so it
     * belongs in both allowlists.
     *
     * @return array<int, string>
     */
    private function offeredRoles(): array
    {
        return [Alert::EVERYONE, ...Role::query()->system()->pluck('name')->all()];
    }

    /**
     * @return array<int, string|int>
     */
    private function offeredCities(): array
    {
        return [Alert::EVERYONE, ...City::query()->where('is_active', true)->pluck('id')->all()];
    }
}
