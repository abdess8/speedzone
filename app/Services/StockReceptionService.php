<?php

namespace App\Services;

use App\Enums\StockReceptionStatus;
use App\Events\StockPickupRequested;
use App\Models\Product;
use App\Models\StockReception;
use App\Models\StockReceptionItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The four hand-overs an inbound shipment goes through.
 *
 * Every method that moves the document writes a journal line before returning, so
 * the timeline is a by-product of the workflow rather than something a caller may
 * forget to do.
 */
class StockReceptionService
{
    public function __construct(
        private readonly StockReceptionReferenceGenerator $references,
        private readonly StockLedgerService $ledger,
    ) {}

    /**
     * Create an inbound shipment slip.
     *
     * @param  array<string, mixed>  $data  Validated payload, with an `items` list.
     */
    public function create(array $data, User $actor): StockReception
    {
        return DB::transaction(function () use ($data, $actor): StockReception {
            // Lines live in their own table, so they are lifted out of the payload
            // before it reaches the model rather than relying on $fillable to
            // discard them — seeders and console commands run unguarded.
            $lines = $data['items'] ?? [];
            unset($data['items']);

            $requestsPickup = ($data['status'] ?? null) === StockReceptionStatus::AWAITING_PICKUP->value;

            $reception = new StockReception($data);
            $reception->seller_id = $actor->accountOwnerId();
            $reception->reference = $this->references->generate();
            $reception->sent_by = $actor->id;
            $reception->status = $requestsPickup
                ? StockReceptionStatus::AWAITING_PICKUP->value
                : StockReceptionStatus::DRAFT->value;

            if ($requestsPickup) {
                $reception->sent_at = $reception->sent_at ?? now()->toDateString();
            }

            $reception->save();

            $this->syncItems($reception, $lines);

            // A slip that skips the draft step opens its timeline on the pickup
            // request, which is what actually happened.
            $reception->recordStatus(null, $reception->statusEnum(), $actor);

            if ($requestsPickup) {
                $this->commitDestination($reception);
                $this->announcePickup($reception);
            }

            return $reception->load('items.product');
        });
    }

    /**
     * Update a slip nobody has been asked to collect yet.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(StockReception $reception, array $data, User $actor): StockReception
    {
        $this->assertVendorEditable($reception);

        return DB::transaction(function () use ($reception, $data, $actor): StockReception {
            $hasLines = array_key_exists('items', $data);
            $lines = $data['items'] ?? [];
            unset($data['items']);

            $reception->fill($data);
            $reception->sent_by = $reception->sent_by ?? $actor->id;
            $reception->save();

            if ($hasLines) {
                $this->syncItems($reception, $lines);
            }

            return $reception->refresh()->load('items.product');
        });
    }

    /**
     * Declare the parcel ready: the lines are frozen and the round is announced.
     *
     * From here the vendor's figures are a declaration to be checked, not a
     * document he may still correct.
     */
    public function markAsSent(StockReception $reception, User $actor): StockReception
    {
        $this->assertTransition($reception, StockReceptionStatus::AWAITING_PICKUP);

        $reception = DB::transaction(function () use ($reception, $actor): StockReception {
            if ($reception->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'items' => __('stock.receptions.errors.no_items'),
                ]);
            }

            $from = $reception->statusEnum();

            $reception->status = StockReceptionStatus::AWAITING_PICKUP->value;
            $reception->sent_at = $reception->sent_at ?? now()->toDateString();
            $reception->sent_by = $reception->sent_by ?? $actor->id;
            $reception->save();

            $this->commitDestination($reception);
            $reception->recordStatus($from, StockReceptionStatus::AWAITING_PICKUP, $actor);

            return $reception->refresh();
        });

        $this->announcePickup($reception);

        return $reception;
    }

    /**
     * The collector signs for what the shop actually handed over.
     *
     * His count supersedes the vendor's declaration as the figure the depot will
     * be held to. It does not touch the catalog: goods in a van are not stock, and
     * crediting them here would let a parcel lost on the road stay sellable.
     *
     * @param  array<int, array{id: int, quantity_collected: int, note?: string|null}>  $lines
     */
    public function collect(
        StockReception $reception,
        array $lines,
        User $actor,
        ?string $collectionNotes = null,
    ): StockReception {
        $this->assertTransition($reception, StockReceptionStatus::COLLECTED);

        return DB::transaction(function () use ($reception, $lines, $actor, $collectionNotes): StockReception {
            $items = $reception->items()->get()->keyBy('id');
            $counted = $this->indexLines($lines, $items);

            foreach ($items as $item) {
                $line = $counted[$item->id] ?? null;

                // An unanswered line means the collector did not take that
                // product, which is a real answer — zero — and not an omission to
                // be filled in with the declared figure.
                $item->quantity_collected = $line === null
                    ? 0
                    : (int) $line['quantity_collected'];

                if ($line !== null && array_key_exists('note', $line)) {
                    $item->note = $line['note'] ?? $item->note;
                }

                $item->save();
            }

            $from = $reception->statusEnum();

            $reception->status = StockReceptionStatus::COLLECTED->value;
            $reception->collected_by = $actor->id;
            $reception->collected_at = now();
            $reception->collection_notes = $collectionNotes ?? $reception->collection_notes;
            $reception->save();

            $reception->recordStatus(
                $from,
                StockReceptionStatus::COLLECTED,
                $actor,
                $this->collectionComment($reception),
            );

            return $reception->refresh()->load('items.product', 'collector');
        });
    }

    /**
     * The collector puts the goods on the road to the depot.
     */
    public function dispatchToDepot(StockReception $reception, User $actor): StockReception
    {
        $this->assertTransition($reception, StockReceptionStatus::IN_TRANSIT);

        return DB::transaction(function () use ($reception, $actor): StockReception {
            $from = $reception->statusEnum();

            $reception->status = StockReceptionStatus::IN_TRANSIT->value;
            $reception->dispatched_at = now();
            $reception->save();

            $reception->recordStatus($from, StockReceptionStatus::IN_TRANSIT, $actor);

            return $reception->refresh();
        });
    }

    /**
     * Close the shipment at the depot and credit what was actually counted.
     *
     * This is the only path by which an inbound shipment reaches the catalog:
     * neither the vendor's declaration nor the collector's count is credited, only
     * the figures the receiving agent signs for.
     *
     * @param  array<int, array{id: int, quantity_received: int, quantity_rejected?: int|null, note?: string|null}>  $lines
     */
    public function validate(
        StockReception $reception,
        array $lines,
        User $actor,
        ?string $receptionNotes = null,
        ?string $receivedAt = null,
    ): StockReception {
        $this->assertTransition($reception, StockReceptionStatus::VALIDATED);

        return DB::transaction(function () use ($reception, $lines, $actor, $receptionNotes, $receivedAt): StockReception {
            $items = $reception->items()->with('product')->get()->keyBy('id');
            $counted = $this->indexLines($lines, $items);

            foreach ($items as $item) {
                $line = $counted[$item->id] ?? null;

                if ($line === null) {
                    // Nothing signed for this line: treated as counted at zero
                    // rather than left null, because a validated document must
                    // not contain unanswered lines. What we were handed goes down
                    // as rejected, so the units are accounted for somewhere.
                    $item->quantity_received = 0;
                    $item->quantity_rejected = $item->baselineQuantity();
                    $item->save();

                    continue;
                }

                $item->quantity_received = (int) $line['quantity_received'];
                $item->quantity_rejected = (int) ($line['quantity_rejected'] ?? 0);
                $item->note = $line['note'] ?? $item->note;
                $item->save();

                $product = $item->product;

                if ($product instanceof Product) {
                    $this->ledger->creditFromReception(
                        product: $product,
                        quantity: (int) $item->quantity_received,
                        reception: $reception,
                        actor: $actor,
                        note: $item->note,
                    );
                }
            }

            $from = $reception->statusEnum();

            $reception->status = StockReceptionStatus::VALIDATED->value;
            $reception->received_by = $actor->id;
            $reception->received_at = $receivedAt ?: now()->toDateString();
            $reception->reception_notes = $receptionNotes ?? $reception->reception_notes;
            $reception->validated_at = now();
            $reception->save();

            $reception->recordStatus($from, StockReceptionStatus::VALIDATED, $actor, $receptionNotes);

            return $reception->refresh()->load('items.product', 'sender', 'collector', 'receiver');
        });
    }

    public function cancel(StockReception $reception, ?string $reason = null, ?User $actor = null): StockReception
    {
        $this->assertTransition($reception, StockReceptionStatus::CANCELLED);

        return DB::transaction(function () use ($reception, $reason, $actor): StockReception {
            $from = $reception->statusEnum();

            $reception->status = StockReceptionStatus::CANCELLED->value;
            $reception->reception_notes = $reason ?: $reception->reception_notes;
            $reception->save();

            $reception->recordStatus($from, StockReceptionStatus::CANCELLED, $actor, $reason);

            return $reception->refresh();
        });
    }

    /**
     * Put the round on the board for every collector who works the shop's city.
     *
     * Fired after the transaction commits, so a listener reading the shipment back
     * cannot see a half-written document — and a broken notification channel can
     * never roll back a hand-over that physically happened.
     */
    private function announcePickup(StockReception $reception): void
    {
        DB::afterCommit(fn () => StockPickupRequested::dispatch($reception));
    }

    /**
     * Spell the collection gap out on the timeline.
     *
     * The figures are on the lines already, but "took 28 of the 30 declared" is
     * the sentence somebody chasing two missing units needs to read, and putting
     * it in the journal dates the discrepancy to the shop rather than to the road.
     */
    private function collectionComment(StockReception $reception): ?string
    {
        $reception->load('items');

        $declared = $reception->totalSent();
        $collected = $reception->totalCollected() ?? 0;

        if ($declared === $collected) {
            return null;
        }

        return __('stock.receptions.history.collection_gap', [
            'collected' => $collected,
            'declared' => $declared,
        ]);
    }

    /**
     * Tie the shipment, and the shop behind it, to a depot.
     *
     * A shop warehouses in exactly one city, and this is where that city gets
     * decided: the first parcel a vendor hands over names his depot, every later
     * one has to agree with it. Keeping the answer on the shop rather than
     * recomputing it from the shipments means a stock order can name the city it
     * leaves from without walking the document history.
     */
    private function commitDestination(StockReception $reception): void
    {
        $store = $reception->store;
        $destinationId = $reception->destination_city_id;

        // Left out by a caller that did not go through the form — a seeder, an
        // API client — while the shop already warehouses somewhere. There is only
        // one possible answer, so use it rather than refuse the shipment.
        if ($destinationId === null && $store?->stock_hub_city_id !== null) {
            $destinationId = (int) $store->stock_hub_city_id;
            $reception->destination_city_id = $destinationId;
            $reception->save();
        }

        if ($destinationId === null) {
            throw ValidationException::withMessages([
                'destination_city_id' => __('stock.receptions.errors.no_destination'),
            ]);
        }

        if ($store === null) {
            return;
        }

        if ($store->stock_hub_city_id === null) {
            $store->stock_hub_city_id = $destinationId;
            $store->save();

            return;
        }

        if ((int) $store->stock_hub_city_id !== (int) $destinationId) {
            throw ValidationException::withMessages([
                'destination_city_id' => __('stock.receptions.errors.wrong_destination', [
                    'city' => (string) $store->stockHubCity?->name,
                ]),
            ]);
        }
    }

    /**
     * Replace the lines of an editable slip.
     *
     * @param  array<int, array{product_id: int, quantity_sent: int, note?: string|null}>  $lines
     */
    private function syncItems(StockReception $reception, array $lines): void
    {
        $keep = [];

        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);

            if ($productId === 0) {
                continue;
            }

            $item = $reception->items()->updateOrCreate(
                ['product_id' => $productId],
                [
                    'quantity_sent' => (int) $line['quantity_sent'],
                    'note' => $line['note'] ?? null,
                ]
            );

            $keep[] = $item->id;
        }

        $reception->items()->whereKeyNot($keep)->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  Collection<int, StockReceptionItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function indexLines(array $lines, Collection $items): array
    {
        $indexed = [];

        foreach ($lines as $line) {
            $id = (int) ($line['id'] ?? 0);

            // A line from another document would credit stock the vendor never
            // shipped, so unknown ids are dropped rather than trusted.
            if ($items->has($id)) {
                $indexed[$id] = $line;
            }
        }

        return $indexed;
    }

    private function assertVendorEditable(StockReception $reception): void
    {
        if (! $reception->isEditableByVendor()) {
            throw ValidationException::withMessages([
                'status' => __('stock.receptions.errors.not_editable'),
            ]);
        }
    }

    private function assertTransition(StockReception $reception, StockReceptionStatus $target): void
    {
        if (! $reception->statusEnum()->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => __('stock.receptions.errors.invalid_transition', [
                    'from' => $reception->statusEnum()->label(),
                    'to' => $target->label(),
                ]),
            ]);
        }
    }
}
