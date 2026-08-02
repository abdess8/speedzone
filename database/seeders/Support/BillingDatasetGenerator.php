<?php

namespace Database\Seeders\Support;

use App\Enums\DriverInvoiceStatus;
use App\Enums\DriverTransactionStatus;
use App\Enums\DriverTransactionType;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\DriverFinanceLog;
use App\Models\DriverInvoice;
use App\Models\DriverTransaction;
use App\Models\Invoice;
use App\Models\InvoiceLog;
use App\Models\Order;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DriverBillingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Generates the money side of the dataset.
 *
 *  - Seller invoices (versements) grouping 5 to 10 settled orders each, with the
 *    same per-line snapshots and totals the application computes itself
 *    ({@see BillingService}): net = encaissé − frais de livraison − frais de retour.
 *  - Driver settlement invoices (décharges de caisse) grouping 5 to 10 delivery
 *    payments, plus the occasional bonus or penalty.
 *
 * Part of the settled orders and earnings is deliberately left un-invoiced so
 * every seller and every driver still has a pending balance to settle.
 */
class BillingDatasetGenerator
{
    public function __construct(
        private readonly DatasetContext $ctx,
        private readonly BillingService $billing,
        private readonly DriverBillingService $driverBilling,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Seller invoices
    |--------------------------------------------------------------------------
    */

    public function seedSellerInvoices(): void
    {
        foreach ($this->ctx->sellers as $seller) {
            $settled = $this->ctx->ordersOf($seller)
                ->whereNull('invoice_id')
                ->whereIn('status', [OrderStatus::DELIVERED->value, OrderStatus::RETURNED->value])
                ->with('sector')
                ->get()
                ->sortBy(fn (Order $order) => $order->completedAt()?->getTimestamp() ?? 0)
                ->values();

            foreach ($settled->groupBy('store_id') as $storeId => $orders) {
                foreach ($this->chunks($orders) as $chunk) {
                    // One batch in five stays pending for the next billing cycle.
                    if (random_int(1, 100) <= 20) {
                        continue;
                    }

                    $this->createSellerInvoice($seller, $storeId ? (int) $storeId : null, $chunk);
                }
            }
        }
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function createSellerInvoice(User $seller, ?int $storeId, Collection $orders): void
    {
        $summary = $this->billing->summarize($orders);

        $completed = $orders
            ->map(fn (Order $order) => $order->completedAt())
            ->filter()
            ->sort()
            ->values();

        $periodStart = ($completed->first() ?? $this->ctx->windowStart)->copy()->startOfDay();
        $periodEnd = ($completed->last() ?? $this->ctx->now)->copy()->endOfDay();
        $generatedAt = $this->ctx->clamp($periodEnd->copy()->addHours(random_int(6, 60)));

        $roll = random_int(1, 100);
        $status = match (true) {
            $roll <= 40 => InvoiceStatus::PAID,
            $roll <= 62 => InvoiceStatus::SENT,
            $roll <= 90 => InvoiceStatus::GENERATED,
            default => InvoiceStatus::CANCELLED,
        };

        $invoice = new Invoice([
            'invoice_number' => 'PENDING',
            'seller_id' => $seller->id,
            'store_id' => $storeId,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'total_orders_count' => $summary['total_orders_count'],
            'delivered_amount' => $summary['delivered_amount'],
            'returned_amount' => $summary['returned_amount'],
            'delivery_fees_total' => $summary['delivery_fees_total'],
            'return_fees_total' => $summary['return_fees_total'],
            'gross_amount' => $summary['gross_amount'],
            'net_amount' => $summary['net_amount'],
            'status' => InvoiceStatus::GENERATED->value,
            'generated_at' => $generatedAt,
            'created_by' => $this->ctx->admin->id,
        ]);
        $this->ctx->saveAt($invoice, $generatedAt);
        $invoice->forceFill([
            'invoice_number' => sprintf('INV-%d-%06d', $generatedAt->year, $invoice->id),
        ])->save();
        $this->ctx->bump('invoices');

        $this->log($invoice, InvoiceLog::ACTION_CREATED, $this->ctx->admin, null, [
            'invoice_number' => $invoice->invoice_number,
            'orders' => $summary['total_orders_count'],
            'net_amount' => $summary['net_amount'],
        ], $generatedAt);

        // A cancelled invoice releases its orders, so no line is attached: the
        // totals stay on the document as a trace of what was cancelled.
        if ($status !== InvoiceStatus::CANCELLED) {
            foreach ($orders as $order) {
                $line = $this->billing->computeLine($order);

                $this->ctx->saveAt($invoice->invoiceOrders()->make([
                    'order_id' => $order->id,
                    'order_amount' => $line['order_amount'],
                    'delivery_fee' => $line['delivery_fee'],
                    'return_fee' => $line['return_fee'],
                    'final_amount' => $line['final_amount'],
                    'order_status_at_invoice' => $line['status'],
                    'order_completed_at' => $line['completed_at'],
                ]), $generatedAt);

                $order->forceFill([
                    'invoice_id' => $invoice->id,
                    'invoice_status' => InvoiceStatus::GENERATED->value,
                ])->save();

                $this->ctx->bump('invoice_lines');
            }
        }

        match ($status) {
            InvoiceStatus::SENT => $this->markInvoiceSent($invoice, $generatedAt),
            InvoiceStatus::PAID => $this->markInvoicePaid($invoice, $generatedAt),
            InvoiceStatus::CANCELLED => $this->cancelInvoice($invoice, $generatedAt),
            default => null,
        };
    }

    private function markInvoiceSent(Invoice $invoice, Carbon $generatedAt): void
    {
        $sentAt = $this->ctx->clamp($generatedAt->copy()->addHours(random_int(1, 30)));

        $this->ctx->updateAt($invoice, ['status' => InvoiceStatus::SENT->value], $sentAt);
        $invoice->orders()->update(['invoice_status' => InvoiceStatus::SENT->value]);
        $this->log($invoice, InvoiceLog::ACTION_STATUS_CHANGED, $this->ctx->admin, InvoiceStatus::GENERATED->value, InvoiceStatus::SENT->value, $sentAt);
    }

    private function markInvoicePaid(Invoice $invoice, Carbon $generatedAt): void
    {
        $paidAt = $this->ctx->clamp($generatedAt->copy()->addHours(random_int(12, 120)));

        $this->ctx->updateAt($invoice, [
            'status' => InvoiceStatus::PAID->value,
            'paid_at' => $paidAt,
            'paid_by' => $this->ctx->admin->id,
            'payment_receipt_attachment' => $this->ctx->storeFile(
                'invoices/receipts',
                'recu_virement_'.$invoice->invoice_number.'.pdf'
            ),
        ], $paidAt);

        $invoice->orders()->update(['invoice_status' => InvoiceStatus::PAID->value]);

        $this->log($invoice, InvoiceLog::ACTION_PAID, $this->ctx->admin, InvoiceStatus::GENERATED->value, [
            'status' => InvoiceStatus::PAID->value,
            'paid_at' => $paidAt->toDateTimeString(),
        ], $paidAt);

        $this->ctx->bump('invoices_paid');
    }

    private function cancelInvoice(Invoice $invoice, Carbon $generatedAt): void
    {
        $cancelledAt = $this->ctx->clamp($generatedAt->copy()->addHours(random_int(2, 48)));

        $this->ctx->updateAt($invoice, ['status' => InvoiceStatus::CANCELLED->value], $cancelledAt);
        $this->log($invoice, InvoiceLog::ACTION_CANCELLED, $this->ctx->admin, InvoiceStatus::GENERATED->value, InvoiceStatus::CANCELLED->value, $cancelledAt);
    }

    /**
     * @param  mixed  $oldValue
     * @param  mixed  $newValue
     */
    private function log(Invoice $invoice, string $action, User $actor, $oldValue, $newValue, Carbon $at): void
    {
        $this->ctx->saveAt($invoice->logs()->make([
            'action' => $action,
            'user_id' => $actor->id,
            'old_value' => is_array($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : $oldValue,
            'new_value' => is_array($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : $newValue,
        ]), $at);
    }

    /*
    |--------------------------------------------------------------------------
    | Driver settlement invoices (cashbox)
    |--------------------------------------------------------------------------
    */

    public function seedDriverInvoices(): void
    {
        foreach ($this->ctx->drivers as $driver) {
            $this->seedManualDriverTransactions($driver);

            $transactions = DriverTransaction::query()
                ->forDriver($driver->id)
                ->billable()
                ->with(['order', 'sector'])
                ->orderBy('created_at')
                ->get()
                ->values();

            foreach ($this->chunks($transactions) as $chunk) {
                $deliveries = $chunk->filter(
                    fn (DriverTransaction $tx) => $tx->transaction_type === DriverTransactionType::DELIVERY_PAYMENT
                );

                // A décharge is only issued for a real batch of deliveries.
                if ($deliveries->count() < 5 || random_int(1, 100) <= 20) {
                    continue;
                }

                $this->createDriverInvoice($driver, $chunk);
            }
        }
    }

    /**
     * Bonuses and penalties an admin adds to a driver's cashbox by hand.
     */
    private function seedManualDriverTransactions(User $driver): void
    {
        if (random_int(1, 100) <= 40) {
            $this->createManualTransaction(
                $driver,
                DriverTransactionType::BONUS,
                round(random_int(5000, 20000) / 100, 2),
                $this->ctx->faker->pick([
                    'Prime de rendement : 100% des colis livrés cette semaine.',
                    'Prime de tournée exceptionnelle (jour férié).',
                    'مكافأة على المردودية الأسبوعية.',
                ])
            );
        }

        if (random_int(1, 100) <= 25) {
            $this->createManualTransaction(
                $driver,
                DriverTransactionType::PENALTY,
                round(random_int(3000, 12000) / 100, 2),
                $this->ctx->faker->pick([
                    'Retenue : colis endommagé pendant la tournée.',
                    'Retenue : écart de caisse constaté.',
                    'خصم بسبب تأخر في تسليم الصندوق.',
                ])
            );
        }

        if (random_int(1, 100) <= 20) {
            $this->createManualTransaction(
                $driver,
                DriverTransactionType::ADJUSTMENT,
                round(random_int(2000, 9000) / 100, 2),
                'Régularisation de frais de carburant.'
            );
        }
    }

    private function createManualTransaction(User $driver, DriverTransactionType $type, float $amount, string $note): void
    {
        $at = $this->ctx->moment();
        $signed = $type === DriverTransactionType::PENALTY ? -abs($amount) : abs($amount);

        $transaction = new DriverTransaction([
            'driver_id' => $driver->id,
            'amount' => round($signed, 2),
            'driver_price_snapshot' => 0,
            'transaction_type' => $type->value,
            'status' => DriverTransactionStatus::CONFIRMED->value,
            'note' => $note,
        ]);
        $this->ctx->saveAt($transaction, $at);
        $this->ctx->bump('driver_transactions');

        $this->ctx->saveAt(new DriverFinanceLog([
            'driver_id' => $driver->id,
            'action' => DriverFinanceLog::ACTION_ADJUSTMENT,
            'user_id' => $this->ctx->admin->id,
            'new_value' => json_encode([
                'type' => $type->value,
                'amount' => round($signed, 2),
                'note' => $note,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]), $at);
    }

    /**
     * @param  Collection<int, DriverTransaction>  $transactions
     */
    private function createDriverInvoice(User $driver, Collection $transactions): void
    {
        $summary = $this->driverBilling->summarize($transactions);

        $periodStart = $transactions->first()->created_at->copy()->startOfDay();
        $periodEnd = $transactions->last()->created_at->copy()->endOfDay();
        $generatedAt = $this->ctx->clamp($periodEnd->copy()->addHours(random_int(4, 40)));

        $roll = random_int(1, 100);
        $status = match (true) {
            $roll <= 45 => DriverInvoiceStatus::PAID,
            $roll <= 90 => DriverInvoiceStatus::GENERATED,
            default => DriverInvoiceStatus::CANCELLED,
        };

        $invoice = new DriverInvoice([
            'invoice_number' => 'PENDING',
            'driver_id' => $driver->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'deliveries_count' => $summary['deliveries_count'],
            'total_amount' => $summary['total_amount'],
            'status' => DriverInvoiceStatus::GENERATED->value,
            'generated_at' => $generatedAt,
            'created_by' => $this->ctx->admin->id,
        ]);
        $this->ctx->saveAt($invoice, $generatedAt);
        $invoice->forceFill([
            'invoice_number' => sprintf('DRV-%d-%06d', $generatedAt->year, $invoice->id),
        ])->save();
        $this->ctx->bump('driver_invoices');

        $this->driverLog($invoice, $driver, DriverFinanceLog::ACTION_INVOICE_CREATED, null, [
            'invoice_number' => $invoice->invoice_number,
            'deliveries' => $summary['deliveries_count'],
            'total_amount' => $summary['total_amount'],
        ], $generatedAt);

        if ($status === DriverInvoiceStatus::CANCELLED) {
            $cancelledAt = $this->ctx->clamp($generatedAt->copy()->addHours(random_int(2, 30)));
            $this->ctx->updateAt($invoice, ['status' => DriverInvoiceStatus::CANCELLED->value], $cancelledAt);
            $this->driverLog($invoice, $driver, DriverFinanceLog::ACTION_INVOICE_CANCELLED, DriverInvoiceStatus::GENERATED->value, DriverInvoiceStatus::CANCELLED->value, $cancelledAt);

            return;
        }

        foreach ($transactions as $transaction) {
            $this->ctx->saveAt($invoice->invoiceTransactions()->make([
                'driver_transaction_id' => $transaction->id,
                'amount_snapshot' => round((float) $transaction->amount, 2),
            ]), $generatedAt);

            $transaction->forceFill(['driver_invoice_id' => $invoice->id])->save();
        }

        if ($status !== DriverInvoiceStatus::PAID) {
            return;
        }

        $paidAt = $this->ctx->clamp($generatedAt->copy()->addHours(random_int(6, 96)));

        $this->ctx->updateAt($invoice, [
            'status' => DriverInvoiceStatus::PAID->value,
            'paid_at' => $paidAt,
            'paid_by' => $this->ctx->admin->id,
            'payment_receipt_attachment' => $this->ctx->storeFile(
                'driver-invoices/receipts',
                'decharge_caisse_'.$invoice->invoice_number.'.pdf'
            ),
        ], $paidAt);

        $invoice->transactions()->update(['status' => DriverTransactionStatus::PAID->value]);

        $this->driverLog($invoice, $driver, DriverFinanceLog::ACTION_INVOICE_PAID, DriverInvoiceStatus::GENERATED->value, [
            'status' => DriverInvoiceStatus::PAID->value,
            'paid_at' => $paidAt->toDateTimeString(),
        ], $paidAt);

        $this->ctx->bump('driver_invoices_paid');
    }

    /**
     * @param  mixed  $oldValue
     * @param  mixed  $newValue
     */
    private function driverLog(DriverInvoice $invoice, User $driver, string $action, $oldValue, $newValue, Carbon $at): void
    {
        $this->ctx->saveAt(new DriverFinanceLog([
            'driver_id' => $driver->id,
            'driver_invoice_id' => $invoice->id,
            'action' => $action,
            'user_id' => $this->ctx->admin->id,
            'old_value' => is_array($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : $oldValue,
            'new_value' => is_array($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : $newValue,
        ]), $at);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Split a list into business-sized batches of 5 to 10 rows, dropping a tail
     * too small to justify a document.
     *
     * @template TItem
     *
     * @param  Collection<int, TItem>  $items
     * @return array<int, Collection<int, TItem>>
     */
    private function chunks(Collection $items): array
    {
        $batches = [];
        $offset = 0;
        $total = $items->count();

        while ($total - $offset >= 5) {
            $size = min(random_int(5, 10), $total - $offset);

            // Never leave a remainder of 1 to 4 rows we could have carried.
            if ($total - $offset - $size < 5 && $total - $offset <= 10) {
                $size = $total - $offset;
            }

            $batches[] = $items->slice($offset, $size)->values();
            $offset += $size;
        }

        return $batches;
    }
}
