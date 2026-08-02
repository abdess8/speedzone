<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportOrdersRequest extends FormRequest
{
    /** Rows accepted in a single batch, mirroring MAX_IMPORT_ROWS on the client. */
    public const MAX_ROWS = 1000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise every row the way StoreOrderRequest normalises a single one.
     *
     * The wizard already sends canonical values, but this endpoint is reachable
     * by anything holding the seller's session, so the payload is treated as
     * untrusted input rather than as output of our own screen.
     */
    protected function prepareForValidation(): void
    {
        $rows = $this->input('orders');

        if (! is_array($rows)) {
            return;
        }

        $this->merge([
            'orders' => array_map(
                fn ($row) => is_array($row) ? $this->normalizeRow($row) : $row,
                $rows
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $declared = $row['payment_method'] ?? null;
        // An unrecognised value is left untouched so `Rule::in` can report it
        // against the right row instead of it being silently coerced to cash.
        $method = ($declared === null || $declared === '')
            ? PaymentMethod::CASH
            : PaymentMethod::tryFrom((string) $declared);

        $row['payment_method'] = $method?->value ?? $declared;

        foreach (['is_fragile', 'can_be_opened', 'option_exchange'] as $flag) {
            $row[$flag] = filter_var($row[$flag] ?? false, FILTER_VALIDATE_BOOL);
        }

        // A cash order declares what the driver collects and mirrors it as the
        // parcel value; a card order was already paid, so nothing is collected.
        if ($method !== PaymentMethod::CARD_PAYMENT) {
            $row['order_value'] = $row['order_amount'] ?? null;
        } else {
            $amount = $row['order_value'] ?? null;
            $row['order_amount'] = null;
            $row['order_value'] = ($amount === null || $amount === '') ? null : $amount;
        }

        return $row;
    }

    /**
     * Per-row rules built from the payload itself.
     *
     * A sector has to belong to the city of *its own* row, and a wildcard rule
     * cannot reach a sibling key, so the rule set is expanded index by index.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'orders' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
        ];

        foreach (array_keys((array) $this->input('orders', [])) as $index) {
            $cityId = $this->input("orders.{$index}.city_id");
            $isCash = $this->input("orders.{$index}.payment_method") === PaymentMethod::CASH->value;

            $rules += [
                "orders.{$index}" => ['required', 'array'],
                "orders.{$index}.customer_first_name" => ['required', 'string', 'max:255'],
                "orders.{$index}.customer_last_name" => ['required', 'string', 'max:255'],
                "orders.{$index}.customer_phone" => ['required', 'string', 'max:50'],
                "orders.{$index}.customer_address" => ['required', 'string', 'max:1000'],
                "orders.{$index}.city_id" => [
                    'required',
                    'integer',
                    Rule::exists('cities', 'id')->where('is_active', true)->whereNull('deleted_at'),
                ],
                "orders.{$index}.sector_id" => [
                    'required',
                    'integer',
                    Rule::exists('sectors', 'id')
                        ->where('is_active', true)
                        ->where('city_id', $cityId)
                        ->whereNull('deleted_at'),
                ],
                "orders.{$index}.payment_method" => ['required', Rule::in(PaymentMethod::values())],
                "orders.{$index}.order_amount" => $isCash
                    ? ['required', 'numeric', 'min:0', 'max:99999999.99']
                    : ['nullable'],
                "orders.{$index}.order_value" => $isCash
                    ? ['nullable']
                    : ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
                "orders.{$index}.notes" => ['nullable', 'string', 'max:2000'],
                "orders.{$index}.is_fragile" => ['boolean'],
                "orders.{$index}.can_be_opened" => ['boolean'],
                "orders.{$index}.option_exchange" => ['boolean'],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orders.*.sector_id.exists' => __('orders.import.validation.sector_city_mismatch'),
            'orders.*.city_id.exists' => __('orders.import.validation.unknown_city'),
            'orders.max' => __('orders.import.validation.too_many_rows', ['max' => self::MAX_ROWS]),
        ];
    }

    /**
     * Row payloads, keyed by their position in the submitted batch.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return array_values($this->validated()['orders']);
    }
}
