<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderChangeHistory;
use App\Models\Sector;
use App\Models\User;

class OrderAuditService
{
    /**
     * Fields tracked for modification history.
     *
     * @var array<string, string>
     */
    public const FIELD_LABELS = [
        'customer_first_name' => 'Customer First Name',
        'customer_last_name' => 'Customer Last Name',
        'customer_phone' => 'Customer Phone',
        'customer_address' => 'Customer Address',
        'city_id' => 'Delivery City',
        'sector_id' => 'Delivery Sector',
        'payment_method' => 'Payment Method',
        'order_amount' => 'Order Amount',
        'order_value' => 'Order Value',
        'delivery_price' => 'Delivery Price',
        'notes' => 'Notes',
        'is_fragile' => 'Fragile Package',
        'can_be_opened' => 'Can Be Opened by Customer',
    ];

    /**
     * @param  array<string, mixed>  $data  Validated update payload.
     * @return array<int, OrderChangeHistory>
     */
    public function recordChanges(Order $order, array $data, User $actor): array
    {
        $changes = $this->detectChanges($order, $data);

        if ($changes === []) {
            return [];
        }

        $records = [];

        foreach ($changes as $field => ['old' => $old, 'new' => $new]) {
            $records[] = $order->changeHistories()->create([
                'changed_by' => $actor->id,
                'field_name' => $field,
                'old_value' => $this->formatValue($field, $old),
                'new_value' => $this->formatValue($field, $new),
            ]);
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function detectChanges(Order $order, array $data): array
    {
        $changes = [];

        foreach (array_keys(self::FIELD_LABELS) as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $old = $order->getAttribute($field);
            $new = $data[$field];

            if ($this->valuesAreEqual($field, $old, $new)) {
                continue;
            }

            $changes[$field] = ['old' => $old, 'new' => $new];
        }

        return $changes;
    }

    public static function fieldLabel(string $fieldName): string
    {
        return self::FIELD_LABELS[$fieldName] ?? ucfirst(str_replace('_', ' ', $fieldName));
    }

    public function formatValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'city_id' => City::query()->find($value)?->name ?? (string) $value,
            'sector_id' => Sector::query()->find($value)?->name ?? (string) $value,
            'payment_method' => $this->formatPaymentMethod($value),
            'order_amount', 'order_value', 'delivery_price' => number_format((float) $value, 2, '.', '').' MAD',
            'is_fragile', 'can_be_opened' => $value ? 'Yes' : 'No',
            default => (string) $value,
        };
    }

    private function formatPaymentMethod(mixed $value): string
    {
        $method = $value instanceof PaymentMethod
            ? $value
            : PaymentMethod::resolve((string) $value);

        return $method->label();
    }

    private function valuesAreEqual(string $field, mixed $old, mixed $new): bool
    {
        if (in_array($field, ['is_fragile', 'can_be_opened'], true)) {
            return (bool) $old === (bool) $new;
        }

        if (in_array($field, ['order_amount', 'order_value', 'delivery_price'], true)) {
            return round((float) ($old ?? 0), 2) === round((float) ($new ?? 0), 2);
        }

        if ($field === 'payment_method') {
            $oldValue = $old instanceof PaymentMethod ? $old->value : (string) $old;
            $newValue = $new instanceof PaymentMethod ? $new->value : (string) $new;

            return $oldValue === $newValue;
        }

        if (in_array($field, ['city_id', 'sector_id'], true)) {
            return (int) $old === (int) $new;
        }

        return (string) ($old ?? '') === (string) ($new ?? '');
    }
}
