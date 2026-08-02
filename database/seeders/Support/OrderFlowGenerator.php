<?php

namespace Database\Seeders\Support;

use App\Enums\DriverTransactionStatus;
use App\Enums\DriverTransactionType;
use App\Enums\OrderFailureReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PickupRequestStatus;
use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Enums\TransferStatus;
use App\Models\City;
use App\Models\DriverFinanceLog;
use App\Models\DriverTransaction;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PickupRequest;
use App\Models\Transfer;
use App\Models\User;
use App\Services\PickupReferenceGenerator;
use App\Services\ReturnReferenceGenerator;
use App\Services\TrackingNumberGenerator;
use App\Services\TransferReferenceGenerator;
use Carbon\Carbon;

/**
 * Generates the operational core of the dataset: orders and the documents that
 * group them (pickup requests, inter-city transfers, returns).
 *
 * Orders are never created in isolation — they are born inside a pickup batch of
 * 5 to 10 parcels, which is what gives every document its business-correct
 * grouping. A batch is then pushed forward step by step:
 *
 *   created → pickup requested → picked up → in depot
 *      → (same city)   in delivery city
 *      → (other city)  transfer created → in transit → received
 *   → out for delivery → delivered / failed / rejected / canceled → return
 *
 * Each step gets a timestamp a few hours after the previous one and the walk
 * stops as soon as the clock would pass "now", so recent batches are still in
 * flight while older ones are settled.
 */
class OrderFlowGenerator
{
    public function __construct(
        private readonly DatasetContext $ctx,
        private readonly TrackingNumberGenerator $tracking,
        private readonly PickupReferenceGenerator $pickupReferences,
        private readonly TransferReferenceGenerator $transferReferences,
        private readonly ReturnReferenceGenerator $returnReferences,
    ) {}

    /**
     * Fill the dataset until at least `$targetOrders` orders exist.
     */
    public function run(int $targetOrders): void
    {
        $sellers = $this->ctx->sellers->values();
        $index = 0;

        while ($this->ctx->count('orders') < $targetOrders) {
            $this->seedPickupBatch($sellers[$index % $sellers->count()]);
            $index++;
        }

        $this->seedTodayBatches();
        $this->seedLooseOrders();
    }

    /**
     * Batches opened in the last hours, so the pipeline always shows parcels
     * caught mid-flight: waiting for the driver, just picked up, sitting in the
     * depot or out for delivery right now.
     */
    private function seedTodayBatches(): void
    {
        $sellers = $this->ctx->sellers->values();

        foreach ([4, 10, 18, 26, 34] as $position => $hoursAgo) {
            $this->seedPickupBatch(
                $sellers[$position % $sellers->count()],
                $this->ctx->now->copy()->subHours($hoursAgo)->subMinutes(random_int(0, 50)),
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Pickup batch: 5 to 10 orders travelling together
    |--------------------------------------------------------------------------
    */

    private function seedPickupBatch(User $seller, ?Carbon $requestedAt = null): void
    {
        $store = $this->ctx->store($seller);
        $origin = $this->ctx->city($seller->city_id) ?? $this->ctx->anyCity();

        // Half of the batches ship inside the seller's own city (no transfer
        // needed), the other half all go to a single remote city so the transfer
        // bordereau carries the whole batch.
        $shipsFar = random_int(1, 100) <= 48;
        $destination = $shipsFar ? $this->ctx->otherCity($origin->id) : $origin;

        $requestedAt = $requestedAt ? $this->ctx->clamp($requestedAt) : $this->ctx->moment();
        $driver = $this->ctx->driverFor($origin);
        $dispatcher = $this->ctx->dispatcher();

        /** @var array<int, Order> $orders */
        $orders = [];
        foreach (range(1, random_int(5, 10)) as $ignored) {
            $createdAt = $this->ctx->clamp($requestedAt->copy()->subMinutes(random_int(90, 3600)));
            $orders[] = $this->createOrder($seller, $destination, $createdAt);
        }

        $pickup = new PickupRequest([
            'reference' => $this->pickupReferences->generate(),
            'created_by' => $seller->id,
            'store_id' => $store?->id,
            'assigned_to' => $driver?->id,
            'status' => PickupRequestStatus::WAITING_FOR_PICKUP->value,
            'pickup_address' => $seller->pickup_address_1 ?? ('Dépôt vendeur, '.$origin->name),
            'number_of_packages' => count($orders),
            'total_orders_amount' => round(array_sum(array_map(
                static fn (Order $order) => (float) $order->order_amount,
                $orders
            )), 2),
            'notes' => $this->ctx->faker->pick([
                'Ramassage du matin, colis prêts à l\'accueil.',
                'Ramassage urgent, le vendeur ferme à 17h.',
                'الطرود جاهزة بالمستودع.',
                'Prévoir un grand coffre, colis volumineux.',
                null,
            ]),
        ]);
        $this->ctx->saveAt($pickup, $requestedAt);
        $this->ctx->bump('pickups');

        $pickup->recordStatus(PickupRequestStatus::WAITING_FOR_PICKUP, $seller, null, 'Demande de ramassage créée par le vendeur.')
            ->forceFill(['created_at' => $requestedAt, 'updated_at' => $requestedAt])->save();

        foreach ($orders as $order) {
            $this->moveOrder(
                $order,
                OrderStatus::WAITING_PICKUP,
                $seller,
                $requestedAt,
                "Ajoutée au ramassage {$pickup->reference}.",
                ['pickup_request_id' => $pickup->id],
                ['pickup' => $pickup->id],
            );
        }

        // A few sellers cancel their request before the driver shows up.
        if (random_int(1, 100) <= 8) {
            $cancelledAt = $this->ctx->after($requestedAt, 2, 20);

            if ($cancelledAt) {
                $this->ctx->updateAt($pickup, ['status' => PickupRequestStatus::CANCELLED->value], $cancelledAt);
                $pickup->recordStatus(PickupRequestStatus::CANCELLED, $seller, PickupRequestStatus::WAITING_FOR_PICKUP->value, 'Ramassage annulé par le vendeur.')
                    ->forceFill(['created_at' => $cancelledAt, 'updated_at' => $cancelledAt])->save();

                foreach ($orders as $order) {
                    $this->moveOrder(
                        $order,
                        OrderStatus::CREATED,
                        $seller,
                        $cancelledAt,
                        'Ramassage annulé — commande libérée.',
                        ['pickup_request_id' => null],
                    );
                }

                return;
            }
        }

        $pickedUpAt = $this->ctx->after($requestedAt, 4, 30);
        if (! $pickedUpAt) {
            return;
        }

        $this->advancePickup($pickup, PickupRequestStatus::PICKED_UP, $driver ?? $dispatcher, $pickedUpAt, $orders);

        // Batches collected in the last two days may still be in the van,
        // waiting for the depot scan.
        $awaitingDepotScan = $pickedUpAt->greaterThan($this->ctx->now->copy()->subDays(2))
            && random_int(1, 100) <= 35;

        $inDepotAt = $awaitingDepotScan ? null : $this->ctx->after($pickedUpAt, 1, 7);
        if (! $inDepotAt) {
            return;
        }

        $this->advancePickup($pickup, PickupRequestStatus::IN_DEPOT, $dispatcher, $inDepotAt, $orders);

        if ($shipsFar) {
            $this->seedTransfer($origin, $destination, $orders, $inDepotAt);

            return;
        }

        // Same city: the platform moves the parcel to the delivery city by itself.
        $arrivedAt = $this->ctx->after($inDepotAt, 1, 9);
        if (! $arrivedAt) {
            return;
        }

        foreach ($orders as $order) {
            $this->moveOrder(
                $order,
                OrderStatus::IN_DELIVERY_CITY,
                null,
                $arrivedAt,
                'Transition automatique : colis déjà dans la ville de livraison.',
                [],
                ['pickup' => $pickup->id],
            );
        }

        $this->seedLastMile($orders, $destination, $arrivedAt);
    }

    /**
     * @param  array<int, Order>  $orders
     */
    private function advancePickup(
        PickupRequest $pickup,
        PickupRequestStatus $to,
        User $actor,
        Carbon $at,
        array $orders,
    ): void {
        $from = $pickup->status instanceof PickupRequestStatus
            ? $pickup->status->value
            : (string) $pickup->status;

        $this->ctx->updateAt($pickup, ['status' => $to->value], $at);
        $pickup->recordStatus($to, $actor, $from, "Ramassage : {$to->value}.")
            ->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

        $orderStatus = $to->orderStatus();

        if (! $orderStatus) {
            return;
        }

        foreach ($orders as $order) {
            $this->moveOrder(
                $order,
                $orderStatus,
                $actor,
                $at,
                "Ramassage {$pickup->reference} : {$to->value}.",
                [],
                ['pickup' => $pickup->id],
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Inter-city transfer (bordereau)
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<int, Order>  $orders
     */
    private function seedTransfer(City $from, City $to, array $orders, Carbon $inDepotAt): void
    {
        $createdAt = $this->ctx->after($inDepotAt, 2, 14);
        if (! $createdAt) {
            return;
        }

        $dispatcher = $this->ctx->dispatcher();
        $lineHauler = $this->ctx->driverFor($from) ?? $this->ctx->driverFor($to);

        $transfer = new Transfer([
            'reference' => $this->transferReferences->generate(),
            'from_city_id' => $from->id,
            'to_city_id' => $to->id,
            'created_by' => $dispatcher->id,
            'assigned_to' => $lineHauler?->id,
            'status' => TransferStatus::CREATED->value,
            'number_of_packages' => count($orders),
            'total_amount' => round(array_sum(array_map(
                static fn (Order $order) => (float) $order->order_amount,
                $orders
            )), 2),
            'notes' => "Bordereau de transfert {$from->name} → {$to->name}.",
        ]);
        $this->ctx->saveAt($transfer, $createdAt);
        $this->ctx->bump('transfers');

        $transfer->statusHistories()->create([
            'old_status' => null,
            'new_status' => TransferStatus::CREATED->value,
            'changed_by' => $dispatcher->id,
            'comment' => 'Bordereau créé au dépôt de '.$from->name.'.',
            'created_at' => $createdAt,
        ]);

        foreach ($orders as $order) {
            $transfer->transferOrders()->create([
                'order_id' => $order->id,
                'created_at' => $createdAt,
            ]);

            $this->moveOrder(
                $order,
                OrderStatus::TRANSFER_CREATED,
                $dispatcher,
                $createdAt,
                "Affectée au bordereau {$transfer->reference}.",
                [],
                ['transfer' => $transfer->id],
            );
        }

        // Some bordereaux are cancelled and their parcels returned to the depot.
        if (random_int(1, 100) <= 8) {
            $cancelledAt = $this->ctx->after($createdAt, 2, 12);

            if ($cancelledAt) {
                $this->advanceTransfer($transfer, TransferStatus::CANCELLED, $dispatcher, $cancelledAt, 'Bordereau annulé : camion indisponible.');

                foreach ($orders as $order) {
                    $this->moveOrder(
                        $order,
                        OrderStatus::IN_DEPOT,
                        $dispatcher,
                        $cancelledAt,
                        'Bordereau annulé — colis revenu au dépôt.',
                        [],
                        ['transfer' => $transfer->id],
                    );
                }

                return;
            }
        }

        // The hub often parks the bordereau in "waiting dispatch" before departure.
        if (random_int(1, 100) <= 35) {
            $waitingAt = $this->ctx->after($createdAt, 1, 8);
            if (! $waitingAt) {
                return;
            }

            $this->advanceTransfer($transfer, TransferStatus::WAITING_DISPATCH, $dispatcher, $waitingAt, 'En attente de départ du camion.');
            $createdAt = $waitingAt;
        }

        $departedAt = $this->ctx->after($createdAt, 2, 16);
        if (! $departedAt) {
            return;
        }

        $this->advanceTransfer($transfer, TransferStatus::IN_TRANSIT, $dispatcher, $departedAt, "Départ du dépôt de {$from->name}.");
        foreach ($orders as $order) {
            $this->moveOrder($order, OrderStatus::IN_TRANSIT, $lineHauler ?? $dispatcher, $departedAt, "Bordereau {$transfer->reference} en route vers {$to->name}.", [], ['transfer' => $transfer->id]);
        }

        $receivedAt = $this->ctx->after($departedAt, 5, 40);
        if (! $receivedAt) {
            return;
        }

        $this->advanceTransfer($transfer, TransferStatus::RECEIVED, $dispatcher, $receivedAt, "Reçu au dépôt de {$to->name}.");
        foreach ($orders as $order) {
            $this->moveOrder($order, OrderStatus::RECEIVED_IN_DESTINATION, $dispatcher, $receivedAt, "Réceptionnée au dépôt de {$to->name}.", [], ['transfer' => $transfer->id]);
        }

        $this->seedLastMile($orders, $to, $receivedAt);
    }

    private function advanceTransfer(Transfer $transfer, TransferStatus $to, User $actor, Carbon $at, string $comment): void
    {
        $from = $transfer->status instanceof TransferStatus
            ? $transfer->status->value
            : (string) $transfer->status;

        $this->ctx->updateAt($transfer, ['status' => $to->value], $at);

        $transfer->statusHistories()->create([
            'old_status' => $from,
            'new_status' => $to->value,
            'changed_by' => $actor->id,
            'comment' => $comment,
            'created_at' => $at,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Last mile
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<int, Order>  $orders
     */
    private function seedLastMile(array $orders, City $city, Carbon $readyAt): void
    {
        foreach ($orders as $order) {
            $driver = $this->ctx->driverFor($city);

            if (! $driver) {
                continue;
            }

            $outAt = $this->ctx->after($readyAt, 2, 32);
            if (! $outAt) {
                continue;
            }

            // Some parcels are corrected by the back office before the round,
            // usually after the customer called about a wrong address.
            if (random_int(1, 100) <= 12) {
                $this->recordCustomerCorrection($order, $city, $outAt);
            }

            $this->moveOrder(
                $order,
                OrderStatus::OUT_FOR_DELIVERY,
                $this->ctx->dispatcher(),
                $outAt,
                "Affectée au livreur {$driver->name} pour la tournée du jour.",
                ['driver_id' => $driver->id, 'assigned_at' => $outAt],
            );

            $closedAt = $this->ctx->after($outAt, 1, 11);
            if (! $closedAt) {
                continue;
            }

            $roll = random_int(1, 100);

            match (true) {
                $roll <= 66 => $this->deliver($order, $driver, $closedAt),
                $roll <= 71 => $this->reject($order, $driver, $closedAt),
                $roll <= 76 => $this->cancel($order, $closedAt),
                default => $this->fail($order, $driver, $closedAt),
            };
        }
    }

    private function deliver(Order $order, User $driver, Carbon $at): void
    {
        $this->moveOrder(
            $order,
            OrderStatus::DELIVERED,
            $driver,
            $at,
            $order->payment_method === PaymentMethod::CASH
                ? 'Livrée — montant encaissé auprès du client.'
                : 'Livrée — commande déjà payée en ligne.',
            ['delivered_at' => $at],
        );

        $this->recordDriverPayment($order, $driver, $at);
        $this->ctx->bump('delivered');

        // A handful of delivered parcels come back (échange, remords du client).
        if (random_int(1, 100) <= 6) {
            $this->seedReturn($order, $at, ReturnReason::CUSTOMER_REQUESTED_RETURN);
        }
    }

    private function fail(Order $order, User $driver, Carbon $at): void
    {
        $reason = $this->ctx->faker->pick([
            OrderFailureReason::CUSTOMER_UNREACHABLE,
            OrderFailureReason::CUSTOMER_UNREACHABLE,
            OrderFailureReason::CUSTOMER_ABSENT,
            OrderFailureReason::CUSTOMER_REFUSED,
            OrderFailureReason::WRONG_ADDRESS,
            OrderFailureReason::POSTPONED,
        ]);

        $note = match ($reason) {
            OrderFailureReason::CUSTOMER_UNREACHABLE => $this->ctx->faker->pick([
                'Téléphone injoignable après 3 tentatives.',
                'الهاتف مغلق بعد ثلاث محاولات.',
            ]),
            OrderFailureReason::CUSTOMER_ABSENT => 'Client absent à l\'adresse indiquée.',
            OrderFailureReason::CUSTOMER_REFUSED => $this->ctx->faker->pick([
                'Le client refuse le colis à la livraison.',
                'الزبون رفض استلام الطرد.',
            ]),
            OrderFailureReason::WRONG_ADDRESS => 'Adresse incomplète, quartier introuvable.',
            default => 'Livraison reportée à la demande du client.',
        };

        $this->moveOrder($order, OrderStatus::FAILED, $driver, $at, null, [
            'failure_reason' => $reason->value,
            'failure_note' => $note,
            'failed_at' => $at,
        ], [], $reason->label().' — '.$note);

        $this->ctx->bump('failed');

        if (random_int(1, 100) <= 72) {
            $this->seedReturn($order, $at, $reason->toReturnReason());
        }
    }

    private function reject(Order $order, User $driver, Carbon $at): void
    {
        $this->moveOrder($order, OrderStatus::REJECTED, $driver, $at, $this->ctx->faker->pick([
            'Colis refusé par le client à la remise.',
            'الزبون رفض الطرد عند التسليم.',
        ]));

        $this->ctx->bump('rejected');
    }

    private function cancel(Order $order, Carbon $at): void
    {
        $this->moveOrder($order, OrderStatus::CANCELED, $this->ctx->dispatcher(), $at, $this->ctx->faker->pick([
            'Annulée à la demande du vendeur.',
            'Annulée : le client a commandé en double.',
            'تم إلغاء الطلب بطلب من التاجر.',
        ]));

        $this->ctx->bump('canceled');
    }

    /*
    |--------------------------------------------------------------------------
    | Returns
    |--------------------------------------------------------------------------
    */

    private function seedReturn(Order $order, Carbon $from, ReturnReason $reason): void
    {
        $openedAt = $this->ctx->after($from, 2, 26);
        if (! $openedAt) {
            return;
        }

        $role = $this->ctx->faker->pick([
            ReturnInitiatedByRole::ADMIN,
            ReturnInitiatedByRole::ADMIN,
            ReturnInitiatedByRole::DRIVER,
            ReturnInitiatedByRole::SELLER,
        ]);

        $actor = match ($role) {
            ReturnInitiatedByRole::SELLER => $order->seller,
            ReturnInitiatedByRole::DRIVER => $order->driver ?? $this->ctx->dispatcher(),
            default => $this->ctx->dispatcher(),
        };

        $return = new OrderReturn([
            'reference' => $this->returnReferences->generate(),
            'order_id' => $order->id,
            'store_id' => $order->store_id,
            'created_by' => $actor->id,
            'initiated_by_role' => $role->value,
            'reason' => $reason->value,
            'status' => ReturnStatus::CREATED->value,
            'current_location_city_id' => $order->city_id,
            'return_address' => $order->customer_address,
            'return_notes' => $this->ctx->faker->pick([
                'Retour à restituer au vendeur avec le bordereau de la semaine.',
                'Colis à vérifier au dépôt avant restitution.',
                'الرجاء إرجاع الطرد إلى التاجر.',
                null,
            ]),
        ]);
        $this->ctx->saveAt($return, $openedAt);
        $this->ctx->bump('returns');

        $return->recordStatus(ReturnStatus::CREATED, $actor, null, 'Retour demandé.')
            ->forceFill(['created_at' => $openedAt])->save();

        $this->moveOrder(
            $order,
            OrderStatus::RETURN_REQUESTED,
            $actor,
            $openedAt,
            "Retour {$return->reference} créé — {$reason->value}.",
            ['return_id' => $return->id, 'is_returned' => false],
            ['return' => $return->id],
        );

        // A few returns are cancelled: the parcel goes back to its previous state.
        if (random_int(1, 100) <= 7) {
            $cancelledAt = $this->ctx->after($openedAt, 2, 20);

            if ($cancelledAt) {
                $this->advanceReturn($return, ReturnStatus::CANCELLED, $actor, $cancelledAt, 'Retour annulé : nouvelle tentative de livraison décidée.');
                $this->moveOrder(
                    $order,
                    OrderStatus::FAILED,
                    $actor,
                    $cancelledAt,
                    'Retour annulé — commande replacée en échec de livraison.',
                    ['return_id' => null, 'is_returned' => false, 'returned_at' => null],
                );

                return;
            }
        }

        $target = $this->ctx->faker->pick([
            ReturnStatus::CREATED,
            ReturnStatus::CREATED,
            ReturnStatus::IN_TRANSIT_TO_DEPOT,
            ReturnStatus::IN_TRANSIT_TO_DEPOT,
            ReturnStatus::RECEIVED_AT_DEPOT,
            ReturnStatus::RECEIVED_AT_DEPOT,
            ReturnStatus::IN_TRANSIT_TO_SELLER,
            ReturnStatus::DELIVERED_TO_SELLER,
            ReturnStatus::DELIVERED_TO_SELLER,
            ReturnStatus::DELIVERED_TO_SELLER,
        ]);

        if ($target === ReturnStatus::CREATED) {
            return;
        }

        $sellerCityId = $order->seller?->city_id;
        $cursor = $openedAt;

        foreach ([
            ReturnStatus::IN_TRANSIT_TO_DEPOT,
            ReturnStatus::RECEIVED_AT_DEPOT,
            ReturnStatus::IN_TRANSIT_TO_SELLER,
            ReturnStatus::DELIVERED_TO_SELLER,
        ] as $status) {
            $at = $this->ctx->after($cursor, 3, 34);
            if (! $at) {
                return;
            }
            $cursor = $at;

            $location = match ($status) {
                ReturnStatus::IN_TRANSIT_TO_SELLER, ReturnStatus::DELIVERED_TO_SELLER => $sellerCityId,
                default => null,
            };

            $this->advanceReturn($return, $status, $this->ctx->dispatcher(), $at, "Retour : {$status->value}.", $location);

            $orderStatus = match ($status) {
                ReturnStatus::IN_TRANSIT_TO_DEPOT => OrderStatus::RETURN_IN_PROGRESS,
                ReturnStatus::DELIVERED_TO_SELLER => OrderStatus::RETURNED,
                default => null,
            };

            if ($orderStatus) {
                $extra = $status === ReturnStatus::DELIVERED_TO_SELLER
                    ? ['is_returned' => true, 'returned_at' => $at]
                    : [];

                $this->moveOrder(
                    $order,
                    $orderStatus,
                    $this->ctx->dispatcher(),
                    $at,
                    "Retour {$return->reference} : {$status->value}.",
                    $extra,
                    ['return' => $return->id],
                );

                if ($status === ReturnStatus::DELIVERED_TO_SELLER) {
                    $this->ctx->bump('returned_orders');
                }
            }

            if ($status === $target) {
                return;
            }
        }
    }

    private function advanceReturn(
        OrderReturn $return,
        ReturnStatus $to,
        User $actor,
        Carbon $at,
        string $comment,
        ?int $locationCityId = null,
    ): void {
        $from = $return->status instanceof ReturnStatus
            ? $return->status->value
            : (string) $return->status;

        $attributes = ['status' => $to->value];
        if ($locationCityId) {
            $attributes['current_location_city_id'] = $locationCityId;
        }

        $this->ctx->updateAt($return, $attributes, $at);
        $return->recordStatus($to, $actor, $from, $comment)
            ->forceFill(['created_at' => $at])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    private function createOrder(User $seller, City $destination, Carbon $createdAt): Order
    {
        $sector = $this->ctx->sector($destination);
        $customer = $this->ctx->faker->customer($destination->name);

        // Cash on delivery dominates Moroccan e-commerce; the rest is already
        // paid online, in which case there is nothing left to collect.
        $isCash = random_int(1, 100) <= 82;
        $value = round(random_int(9000, 240000) / 100, 2);

        $order = new Order([
            'tracking_number' => $this->tracking->generate(),
            'seller_id' => $seller->id,
            'store_id' => $this->ctx->store($seller)?->id,
            'customer_first_name' => $customer['first_name'],
            'customer_last_name' => $customer['last_name'],
            'customer_phone' => $customer['phone'],
            'customer_address' => $customer['address'],
            'city_id' => $destination->id,
            'sector_id' => $sector->id,
            'payment_method' => $isCash ? PaymentMethod::CASH->value : PaymentMethod::CARD_PAYMENT->value,
            'order_amount' => $isCash ? $value : null,
            'order_value' => $value,
            'delivery_price' => (float) $sector->delivery_price,
            'notes' => $customer['notes'],
            'is_fragile' => random_int(1, 100) <= 22,
            'can_be_opened' => random_int(1, 100) <= 55,
            'option_exchange' => random_int(1, 100) <= 8,
            'status' => OrderStatus::CREATED->value,
        ]);

        $this->ctx->saveAt($order, $createdAt);
        $this->ctx->bump('orders');

        if ($customer['arabic']) {
            $this->ctx->bump('arabic_orders');
        }

        $this->recordOrderStatus($order, OrderStatus::CREATED, $seller, $createdAt, 'Commande créée par le vendeur.');

        return $order;
    }

    /**
     * Move an order to its next status, stamping the order and its audit trail
     * at the same past moment.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, int>  $links  pickup / transfer / return ids
     */
    private function moveOrder(
        Order $order,
        OrderStatus $status,
        ?User $actor,
        Carbon $at,
        ?string $comment,
        array $attributes = [],
        array $links = [],
        ?string $historyComment = null,
    ): void {
        $this->ctx->updateAt($order, $attributes + ['status' => $status->value], $at);
        $this->recordOrderStatus($order, $status, $actor, $at, $historyComment ?? $comment, $links);
    }

    /**
     * @param  array<string, int>  $links
     */
    private function recordOrderStatus(
        Order $order,
        OrderStatus $status,
        ?User $actor,
        Carbon $at,
        ?string $comment,
        array $links = [],
    ): void {
        $order->recordStatus(
            $status,
            $actor,
            $comment,
            isSystem: $actor === null,
            pickupRequestId: $links['pickup'] ?? null,
            transferId: $links['transfer'] ?? null,
            returnId: $links['return'] ?? null,
        )->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

        $this->ctx->bump('status_histories');
    }

    /**
     * Address or phone correction applied by the back office, kept in the order
     * modification history so the change is auditable field by field.
     */
    private function recordCustomerCorrection(Order $order, City $city, Carbon $at): void
    {
        $actor = $this->ctx->dispatcher();
        $arabic = str_contains((string) $order->customer_address, 'ي');

        [$field, $oldValue, $newValue] = random_int(1, 100) <= 65
            ? ['customer_address', $order->customer_address, $this->ctx->faker->address($city->name, $arabic)]
            : ['customer_phone', $order->customer_phone, $this->ctx->faker->phone()];

        $this->ctx->updateAt($order, [$field => $newValue], $at);

        $this->ctx->saveAt($order->changeHistories()->make([
            'changed_by' => $actor->id,
            'field_name' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]), $at);

        $this->ctx->bump('order_changes');
    }

    /**
     * Delivery earning owed to the driver, snapshotted from the sector price —
     * the row a driver settlement invoice is later built from.
     */
    private function recordDriverPayment(Order $order, User $driver, Carbon $at): void
    {
        $order->loadMissing('sector');
        $amount = round((float) ($order->sector?->delivery_driver_price ?? 0), 2);

        $transaction = new DriverTransaction([
            'driver_id' => $driver->id,
            'order_id' => $order->id,
            'sector_id' => $order->sector_id,
            'amount' => $amount,
            'driver_price_snapshot' => $amount,
            'transaction_type' => DriverTransactionType::DELIVERY_PAYMENT->value,
            'status' => DriverTransactionStatus::CONFIRMED->value,
        ]);
        $this->ctx->saveAt($transaction, $at);
        $this->ctx->bump('driver_transactions');

        $this->ctx->saveAt(new DriverFinanceLog([
            'driver_id' => $driver->id,
            'action' => DriverFinanceLog::ACTION_TRANSACTION_CREATED,
            'user_id' => $driver->id,
            'new_value' => json_encode([
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'amount' => $amount,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]), $at);
    }

    /*
    |--------------------------------------------------------------------------
    | Orders sitting outside any document yet
    |--------------------------------------------------------------------------
    */

    /**
     * Fresh orders typed in by sellers today: not grouped in any pickup yet, so
     * the "new orders" and "pickup requested" screens are never empty.
     */
    private function seedLooseOrders(): void
    {
        foreach ($this->ctx->sellers as $seller) {
            $origin = $this->ctx->city($seller->city_id) ?? $this->ctx->anyCity();

            foreach (range(1, random_int(1, 3)) as $ignored) {
                $createdAt = $this->ctx->now->copy()
                    ->subDays(random_int(0, 3))
                    ->setTime(random_int(9, 20), (int) $this->ctx->faker->pick([0, 15, 30, 45]));

                $order = $this->createOrder($seller, random_int(1, 100) <= 60 ? $origin : $this->ctx->anyCity(), $this->ctx->clamp($createdAt));

                // A couple of them have already been declared for pickup.
                if (random_int(1, 100) <= 25) {
                    $requestedAt = $this->ctx->after($order->created_at, 1, 6);

                    if ($requestedAt) {
                        $this->moveOrder(
                            $order,
                            OrderStatus::PICKUP_REQUESTED,
                            $seller,
                            $requestedAt,
                            'Ramassage demandé par le vendeur.',
                        );
                    }
                }
            }
        }
    }
}
