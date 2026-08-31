<?php

namespace App\Http\Requests;

use App\Models\StockReception;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the collector signs for at the shop counter.
 *
 * Shaped like the depot count, and capped the same way: a vendor sometimes hands
 * over more than his slip says, but the surplus has to be a deliberate figure
 * rather than a stray keystroke.
 */
class CollectStockReceptionRequest extends FormRequest
{
    /** Head-room above the declared quantity a collector may sign for. */
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
            'collection_notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['array'],
            'items.*.id' => [
                'required',
                'integer',
                'distinct',
                // Bound to this document: a line id from another shipment would
                // record a count against goods this collector never saw.
                Rule::exists('stock_reception_items', 'id')->where('stock_reception_id', $receptionId),
            ],
            'items.*.quantity_collected' => ['required', 'integer', 'min:0', 'max:1000000'],
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

            $declared = $reception->items()->pluck('quantity_sent', 'id');

            foreach ((array) $this->input('items', []) as $index => $line) {
                $sent = (int) ($declared[(int) ($line['id'] ?? 0)] ?? 0);

                if ((int) ($line['quantity_collected'] ?? 0) > $sent + self::OVERAGE_ALLOWANCE) {
                    $v->errors()->add(
                        "items.{$index}.quantity_collected",
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
