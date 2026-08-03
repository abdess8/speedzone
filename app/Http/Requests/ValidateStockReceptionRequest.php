<?php

namespace App\Http\Requests;

use App\Models\StockReception;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the depot signs for.
 *
 * Received and rejected quantities are allowed to exceed what was handed to us — a
 * parcel does sometimes contain more than the paperwork says — but the excess has
 * to be deliberate, so it is capped rather than silently accepted.
 *
 * The cap is measured against the collector's count when there is one: he is the
 * last person to have seen the goods, and holding the depot to a figure already
 * superseded at the shop door would flag every corrected line as an overage.
 */
class ValidateStockReceptionRequest extends FormRequest
{
    /** Head-room above the declared quantity a receiving agent may sign for. */
    private const OVERAGE_ALLOWANCE = 1000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reception = $this->route('reception');
        $receptionId = $reception instanceof StockReception ? $reception->getKey() : 0;

        return [
            'received_at' => ['nullable', 'date'],
            'reception_notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['array'],
            'items.*.id' => [
                'required',
                'integer',
                'distinct',
                // Bound to this document: a line id from another shipment would
                // credit stock the vendor never sent us.
                Rule::exists('stock_reception_items', 'id')->where('stock_reception_id', $receptionId),
            ],
            'items.*.quantity_received' => ['required', 'integer', 'min:0', 'max:1000000'],
            'items.*.quantity_rejected' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $reception = $this->route('reception');

            if (! $reception instanceof StockReception || $v->errors()->isNotEmpty()) {
                return;
            }

            $handedOver = $reception->items()
                ->get(['id', 'quantity_sent', 'quantity_collected'])
                ->mapWithKeys(fn ($item) => [$item->id => $item->baselineQuantity()]);

            foreach ((array) $this->input('items', []) as $index => $line) {
                $sent = (int) ($handedOver[(int) ($line['id'] ?? 0)] ?? 0);
                $counted = (int) ($line['quantity_received'] ?? 0) + (int) ($line['quantity_rejected'] ?? 0);

                if ($counted > $sent + self::OVERAGE_ALLOWANCE) {
                    $v->errors()->add(
                        "items.{$index}.quantity_received",
                        __('stock.receptions.errors.over_declared', ['sent' => $sent])
                    );
                }
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lines(): array
    {
        return array_values($this->validated()['items']);
    }
}
