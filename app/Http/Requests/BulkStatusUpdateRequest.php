<?php

namespace App\Http\Requests;

use App\Enums\BulkStatusEntityType;
use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Services\StatusTransitionAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape check only.
 *
 * Authorisation is deliberately left to {@see BulkStatusUpdateService}: a batch
 * is refused item by item, with a reason the operator can act on, rather than
 * rejected wholesale because one of two hundred rows moved while he was
 * reading. The one thing checked up front is that the user holds *some* grant
 * into the requested target — a request with none is not a partly stale batch,
 * it is someone calling the endpoint by hand.
 */
class BulkStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entity = BulkStatusEntityType::tryFrom((string) $this->input('entity_type'));

        if ($entity === null) {
            return false;
        }

        return in_array(
            (string) $this->input('to_status'),
            app(StatusTransitionAccessService::class)->targets($this->user(), $entity),
            true
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = $this->entity() === BulkStatusEntityType::RETURN
            ? ReturnStatus::values()
            : OrderStatus::values();

        return [
            'entity_type' => ['required', Rule::in(BulkStatusEntityType::values())],
            'to_status' => ['required', 'string', Rule::in($statuses)],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.id' => ['required', 'integer'],
            // Optional so a scanned selection can be submitted without one; when
            // present it is the optimistic lock that refuses a stale row.
            'items.*.from_status' => ['nullable', 'string', Rule::in($statuses)],
            'comment' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function entity(): BulkStatusEntityType
    {
        return BulkStatusEntityType::tryFrom((string) $this->input('entity_type'))
            ?? BulkStatusEntityType::ORDER;
    }

    /**
     * @return array<int, array{id: int, from_status?: string|null}>
     */
    public function items(): array
    {
        return array_map(
            static fn (array $item): array => [
                'id' => (int) $item['id'],
                'from_status' => $item['from_status'] ?? null,
            ],
            $this->input('items', [])
        );
    }
}
