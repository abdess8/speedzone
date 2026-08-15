<?php

namespace App\Services;

use App\Enums\BulkStatusEntityType;
use App\Enums\BulkStatusFailureReason;
use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Models\BulkStatusChangeLog;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The bulk status editor: which items qualify, and what happens when a batch is
 * submitted.
 *
 * Two rules shape everything here.
 *
 * The first is that this service adds no way to reach an order or a return. It
 * builds its board on top of OrderQueryService and ReturnQueryService, so the
 * data scoping that governs the normal lists — read.all / read.assigned /
 * read.own, the active shop, the partner split — governs this screen too, and a
 * widening of the batch filters can never widen the perimeter.
 *
 * The second is that it applies no status itself. Every write goes through
 * OrderTransitionService or ReturnTransitionService, which own the graph, the
 * target-status permission, the history entry and the side effects (driver
 * payment, partner sync, city routing). A batch is therefore exactly a loop of
 * the single-item action the operator could have performed by hand, which is
 * the only way to be sure the two cannot diverge.
 */
class BulkStatusUpdateService
{
    private const MAX_BATCH_SIZE = 500;

    public function __construct(
        private readonly StatusTransitionAccessService $access,
        private readonly OrderQueryService $orderQuery,
        private readonly ReturnQueryService $returnQuery,
        private readonly OrderTransitionService $orderTransitions,
        private readonly ReturnTransitionService $returnTransitions,
    ) {}

    /**
     * Items the user may move into `$target`, honouring his data perimeter, the
     * transitions he is granted, and whatever he typed in the filters.
     *
     * @return LengthAwarePaginator<int, Model>
     */
    public function eligible(
        Request $request,
        User $user,
        BulkStatusEntityType $entity,
        string $target,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $sources = $this->access->resolveSources($user, $entity, $target, $request->input('source_status'));

        if ($sources === []) {
            // No legal source means no eligible item — not "every item".
            return $this->query($request, $user, $entity, [])->paginate($perPage)->withQueryString();
        }

        return $this->query($request, $user, $entity, $sources)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Resolve a scanned code to an item and say whether it may be moved.
     *
     * Runs the identical checks the list runs, in the same order, so a scanner
     * can never add a parcel the manual board would have refused to show.
     *
     * @return array{valid: bool, message: string, item: array<string, mixed>|null}
     */
    public function resolveScan(
        User $user,
        BulkStatusEntityType $entity,
        string $target,
        string $scan,
    ): array {
        $reference = $this->parseReference($scan);

        if ($reference === null) {
            return $this->scanFailure(__('bulk_status.scan.unreadable'));
        }

        $model = $this->findByReference($user, $entity, $reference);

        if ($model === null) {
            return $this->scanFailure(__('bulk_status.scan.not_found', ['reference' => $reference]));
        }

        if (! $user->can('updateStatus', $model)) {
            return $this->scanFailure(__('bulk_status.scan.inaccessible', ['reference' => $reference]));
        }

        $from = $this->statusOf($model);

        if (! $this->access->allows($user, $entity, $from, $target)) {
            return $this->scanFailure(__('bulk_status.scan.transition_forbidden', [
                'reference' => $reference,
                'from' => $entity->statusLabel($from),
                'to' => $entity->statusLabel($target),
            ]));
        }

        return [
            'valid' => true,
            'message' => '',
            'item' => $this->present($model, $entity, $target),
        ];
    }

    /**
     * Apply the batch, item by item.
     *
     * Deliberately not one transaction: a parcel a colleague delivered thirty
     * seconds ago must not cancel the other 199 hand-overs the operator is
     * recording. Each item carries the status it was selected under, and an
     * item that has moved since is refused rather than overwritten — the
     * optimistic lock that makes a stale board safe to submit from.
     *
     * @param  array<int, array{id: int, from_status?: string|null}>  $items
     * @return array{
     *     batch_id: string,
     *     succeeded: int,
     *     failed: int,
     *     results: array<int, array<string, mixed>>
     * }
     */
    public function execute(
        User $user,
        BulkStatusEntityType $entity,
        string $target,
        array $items,
        ?string $comment = null,
        string $source = BulkStatusChangeLog::SOURCE_BULK_EDIT,
    ): array {
        $batchId = (string) Str::uuid();
        $items = array_slice($items, 0, self::MAX_BATCH_SIZE);
        $models = $this->findForExecution($user, $entity, array_column($items, 'id'));

        $results = [];
        $succeeded = 0;

        foreach ($items as $item) {
            $result = $this->applyOne(
                $user,
                $entity,
                $target,
                (int) $item['id'],
                $item['from_status'] ?? null,
                $models[(int) $item['id']] ?? null,
                $comment,
            );

            $this->log($batchId, $user, $entity, $target, $result, $source);

            $succeeded += $result['successful'] ? 1 : 0;
            $results[] = $result;
        }

        return [
            'batch_id' => $batchId,
            'succeeded' => $succeeded,
            'failed' => count($results) - $succeeded,
            'results' => $results,
        ];
    }

    /**
     * The five checks every item goes through, in the order that produces the
     * most useful refusal message.
     *
     * @return array<string, mixed>
     */
    private function applyOne(
        User $user,
        BulkStatusEntityType $entity,
        string $target,
        int $id,
        ?string $expectedFrom,
        ?Model $model,
        ?string $comment,
    ): array {
        if ($model === null) {
            return $this->failure($id, null, null, $target, BulkStatusFailureReason::NOT_FOUND);
        }

        $reference = $this->referenceOf($model);
        $from = $this->statusOf($model);

        if (! $user->can('updateStatus', $model)) {
            return $this->failure($id, $reference, $from, $target, BulkStatusFailureReason::INACCESSIBLE);
        }

        // The board the operator ticked may be minutes old.
        if ($expectedFrom !== null && $expectedFrom !== $from) {
            return $this->failure(
                $id,
                $reference,
                $from,
                $target,
                BulkStatusFailureReason::STATUS_CHANGED,
                __('bulk_status.failures.status_changed_detail', [
                    'expected' => $entity->statusLabel($expectedFrom),
                    'actual' => $entity->statusLabel($from),
                ])
            );
        }

        if (! $this->access->allows($user, $entity, $from, $target)) {
            return $this->failure($id, $reference, $from, $target, BulkStatusFailureReason::TRANSITION_NOT_ALLOWED);
        }

        try {
            $this->transition($model, $entity, $target, $user, $comment);
        } catch (AuthorizationException $e) {
            return $this->failure(
                $id,
                $reference,
                $from,
                $target,
                BulkStatusFailureReason::PERMISSION_DENIED,
                $e->getMessage()
            );
        } catch (ValidationException $e) {
            return $this->failure(
                $id,
                $reference,
                $from,
                $target,
                BulkStatusFailureReason::BUSINESS_RULE,
                collect($e->errors())->flatten()->first()
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->failure(
                $id,
                $reference,
                $from,
                $target,
                BulkStatusFailureReason::BUSINESS_RULE,
                __('bulk_status.failures.unexpected')
            );
        }

        return [
            'id' => $id,
            'reference' => $reference,
            'from_status' => $from,
            'from_status_label' => $entity->statusLabel($from),
            'to_status' => $target,
            'to_status_label' => $entity->statusLabel($target),
            'successful' => true,
            'failure_reason' => null,
            'failure_message' => null,
        ];
    }

    private function transition(
        Model $model,
        BulkStatusEntityType $entity,
        string $target,
        User $user,
        ?string $comment,
    ): void {
        $note = trim(implode(' — ', array_filter([__('bulk_status.audit.comment'), $comment])));

        if ($entity === BulkStatusEntityType::ORDER) {
            /** @var Order $model */
            $this->orderTransitions->transition($model, $target, $user, $note);

            return;
        }

        /** @var OrderReturn $model */
        $this->returnTransitions->transition($model, $target, $user, $note);
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(
        int $id,
        ?string $reference,
        ?string $from,
        string $target,
        BulkStatusFailureReason $reason,
        ?string $message = null,
    ): array {
        return [
            'id' => $id,
            'reference' => $reference,
            'from_status' => $from,
            'from_status_label' => $from !== null ? $this->safeLabel($from) : null,
            'to_status' => $target,
            'to_status_label' => $this->safeLabel($target),
            'successful' => false,
            'failure_reason' => $reason->value,
            'failure_message' => $message ?? $reason->label(),
        ];
    }

    /**
     * A status that no longer exists in the enum must not break the report the
     * operator is reading.
     */
    private function safeLabel(string $status): string
    {
        return OrderStatus::tryFrom($status)?->label()
            ?? ReturnStatus::tryFrom($status)?->label()
            ?? $status;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function log(
        string $batchId,
        User $user,
        BulkStatusEntityType $entity,
        string $target,
        array $result,
        string $source,
    ): void {
        BulkStatusChangeLog::create([
            'batch_id' => $batchId,
            'user_id' => $user->id,
            'entity_type' => $entity->value,
            'entity_id' => $result['id'],
            'reference' => $result['reference'],
            'from_status' => $result['from_status'],
            'to_status' => $target,
            'successful' => $result['successful'],
            'failure_reason' => $result['failure_reason'],
            'failure_message' => $result['failure_message'] !== null
                ? Str::limit((string) $result['failure_message'], 490)
                : null,
            'source' => $source,
        ]);
    }

    /**
     * Board query: the normal list query, narrowed to the legal source statuses.
     *
     * @param  array<int, string>  $sources
     * @return Builder<Model>
     */
    private function query(
        Request $request,
        User $user,
        BulkStatusEntityType $entity,
        array $sources,
    ): Builder {
        // The source list is injected rather than trusted from the request:
        // `status` is the parameter both query services already understand, and
        // routing the filter through it means the batch cannot ask for a status
        // the access service did not hand back.
        $scoped = $this->requestWithStatuses($request, $sources);

        if ($entity === BulkStatusEntityType::ORDER) {
            return $this->orderQuery
                ->build($scoped, $user, ['city:id,name', 'sector:id,name', 'seller:id,name,first_name,last_name'])
                ->when($sources === [], fn (Builder $q) => $q->whereRaw('1 = 0'));
        }

        return $this->returnQuery
            ->build($scoped, $user)
            ->when($sources === [], fn (Builder $q) => $q->whereRaw('1 = 0'));
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function requestWithStatuses(Request $request, array $statuses): Request
    {
        $scoped = Request::createFrom($request);
        $scoped->merge(['status' => $statuses]);
        // A source filter can only ever narrow: the group shortcut would widen.
        $scoped->request->remove('status_group');
        $scoped->query->remove('status_group');

        return $scoped;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, Model>
     */
    private function findForExecution(User $user, BulkStatusEntityType $entity, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $request = Request::create('/');

        $query = $entity === BulkStatusEntityType::ORDER
            ? $this->orderQuery->build($request, $user, [])
            : $this->returnQuery->build($request, $user);

        return $query->whereIn($entity === BulkStatusEntityType::ORDER ? 'orders.id' : 'returns.id', $ids)
            ->get()
            ->keyBy('id')
            ->all();
    }

    private function findByReference(User $user, BulkStatusEntityType $entity, string $reference): ?Model
    {
        $request = Request::create('/');

        if ($entity === BulkStatusEntityType::ORDER) {
            return $this->orderQuery
                ->build($request, $user, ['city:id,name', 'sector:id,name', 'seller:id,name,first_name,last_name'])
                ->where('tracking_number', $reference)
                ->first();
        }

        // A return label carries the return's own reference; a parcel on the
        // shelf still wears the order's, so both are accepted.
        return $this->returnQuery
            ->build($request, $user)
            ->where(function (Builder $q) use ($reference): void {
                $q->where('reference', $reference)
                    ->orWhereHas('order', fn (Builder $oq) => $oq->where('tracking_number', $reference));
            })
            ->first();
    }

    /**
     * Pull a reference out of a scanned string.
     *
     * Mirrors the client-side `parseTrackingNumber()`: shipping labels encode
     * the public tracking URL, a hand-held wedge scanner types the bare code.
     */
    private function parseReference(string $scan): ?string
    {
        $value = trim($scan);

        if ($value === '') {
            return null;
        }

        if (preg_match('#/(?:orders|returns)/([A-Za-z0-9]+-[0-9]{4}-[0-9]+)#i', $value, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/^([A-Za-z0-9]+-[0-9]{4}-[0-9]+)$/', $value, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    private function statusOf(Model $model): string
    {
        $status = $model->getAttribute('status');

        return $status instanceof OrderStatus || $status instanceof ReturnStatus
            ? $status->value
            : (string) $status;
    }

    private function referenceOf(Model $model): ?string
    {
        return $model instanceof Order
            ? $model->tracking_number
            : $model->getAttribute('reference');
    }

    /**
     * Normalised row shape shared by both entities.
     *
     * The bulk board is one component for orders and returns, so the difference
     * between them lives here rather than in the template: identity, the two
     * statuses of the transition, and a list of already-available details the
     * operator recognises from the ordinary list.
     *
     * @return array<string, mixed>
     */
    public function present(Model $model, BulkStatusEntityType $entity, string $target): array
    {
        $from = $this->statusOf($model);

        return [
            'id' => $model->getKey(),
            'reference' => $this->referenceOf($model),
            'from_status' => $entity->statusDescriptor($from),
            'to_status' => $entity->statusDescriptor($target),
            'customer' => $this->customerOf($model, $entity),
            'details' => $this->detailsOf($model, $entity),
            'created_at' => $model->getAttribute('created_at')?->toIso8601String(),
        ];
    }

    /**
     * @return array{name: string|null, phone: string|null, address: string|null}
     */
    private function customerOf(Model $model, BulkStatusEntityType $entity): array
    {
        if ($entity === BulkStatusEntityType::ORDER) {
            /** @var Order $model */
            return [
                'name' => $model->customer_full_name,
                'phone' => $model->customer_phone,
                'address' => $model->customer_address,
            ];
        }

        /** @var OrderReturn $model */
        return [
            'name' => $model->effectiveCustomerName(),
            'phone' => $model->effectiveCustomerPhone(),
            'address' => $model->effectiveAddress(),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string|null}>
     */
    private function detailsOf(Model $model, BulkStatusEntityType $entity): array
    {
        if ($entity === BulkStatusEntityType::ORDER) {
            /** @var Order $model */
            return array_values(array_filter([
                ['label' => __('bulk_status.details.city'), 'value' => $model->city?->name],
                ['label' => __('bulk_status.details.sector'), 'value' => $model->sector?->name],
                ['label' => __('bulk_status.details.seller'), 'value' => $model->seller?->full_name],
                ['label' => __('bulk_status.details.to_collect'), 'value' => $this->money($model->total_amount)],
            ], fn (array $row) => filled($row['value'])));
        }

        /** @var OrderReturn $model */
        return array_values(array_filter([
            ['label' => __('bulk_status.details.order'), 'value' => $model->order?->tracking_number],
            ['label' => __('bulk_status.details.current_city'), 'value' => $model->currentLocationCity?->name],
            ['label' => __('bulk_status.details.reason'), 'value' => $model->reason],
            ['label' => __('bulk_status.details.driver'), 'value' => $model->assignedDriver?->full_name],
        ], fn (array $row) => filled($row['value'])));
    }

    private function money(mixed $amount): ?string
    {
        return $amount === null ? null : number_format((float) $amount, 2).' '.__('common.currency_mad');
    }

    /**
     * @return array{valid: bool, message: string, item: null}
     */
    private function scanFailure(string $message): array
    {
        return ['valid' => false, 'message' => $message, 'item' => null];
    }
}
