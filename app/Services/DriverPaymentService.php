<?php

namespace App\Services;

use App\Enums\DriverTransactionStatus;
use App\Enums\DriverTransactionType;
use App\Enums\OrderStatus;
use App\Models\DriverFinanceLog;
use App\Models\DriverTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates and manages driver financial transactions: the automatic delivery
 * payment generated when an order is delivered, plus manual adjustments, bonuses
 * and penalties added by an admin. Every mutation is transactional and audited.
 */
class DriverPaymentService
{
    /**
     * Generate the delivery payment for a freshly delivered order.
     *
     * The amount is snapshotted from the sector driver price so subsequent rate
     * changes never affect this transaction. Idempotent: a second call for the
     * same order returns the existing transaction (one payment per order).
     */
    public function recordDeliveryPayment(Order $order, ?User $actor = null): ?DriverTransaction
    {
        if (! $order->driver_id) {
            return null;
        }

        $status = $order->status instanceof OrderStatus ? $order->status : OrderStatus::from((string) $order->status);
        if ($status !== OrderStatus::DELIVERED) {
            return null;
        }

        return DB::transaction(function () use ($order, $actor): DriverTransaction {
            $existing = DriverTransaction::query()
                ->where('order_id', $order->id)
                ->where('transaction_type', DriverTransactionType::DELIVERY_PAYMENT->value)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $order->loadMissing('sector');
            $snapshot = round((float) ($order->sector?->delivery_driver_price ?? 0), 2);

            $transaction = DriverTransaction::create([
                'driver_id' => $order->driver_id,
                'order_id' => $order->id,
                'sector_id' => $order->sector_id,
                'amount' => $snapshot,
                'driver_price_snapshot' => $snapshot,
                'transaction_type' => DriverTransactionType::DELIVERY_PAYMENT->value,
                // A delivered order confirms the earning, making it billable.
                'status' => DriverTransactionStatus::CONFIRMED->value,
            ]);

            DriverFinanceLog::create([
                'driver_id' => $order->driver_id,
                'action' => DriverFinanceLog::ACTION_TRANSACTION_CREATED,
                'user_id' => $actor?->id,
                'new_value' => json_encode([
                    'order_id' => $order->id,
                    'tracking_number' => $order->tracking_number,
                    'amount' => $snapshot,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return $transaction;
        });
    }

    /**
     * Add a manual adjustment / bonus / penalty for a driver.
     *
     * @throws InvalidArgumentException when asked to forge a delivery payment,
     *                                  which may only come from a delivered order.
     */
    public function recordManualTransaction(
        User $driver,
        DriverTransactionType $type,
        float $amount,
        ?string $note,
        ?User $actor = null,
        ?Order $order = null,
    ): DriverTransaction {
        if (! $type->isManual()) {
            throw new InvalidArgumentException("Transaction type {$type->value} cannot be created manually.");
        }

        // Penalties are stored as negative amounts so totals net out correctly.
        $signed = $type === DriverTransactionType::PENALTY ? -abs($amount) : abs($amount);

        return DB::transaction(function () use ($driver, $type, $signed, $note, $actor, $order): DriverTransaction {
            $transaction = DriverTransaction::create([
                'driver_id' => $driver->id,
                'order_id' => $order?->id,
                'sector_id' => $order?->sector_id,
                'amount' => round($signed, 2),
                'driver_price_snapshot' => 0,
                'transaction_type' => $type->value,
                'status' => DriverTransactionStatus::CONFIRMED->value,
                'note' => $note,
            ]);

            DriverFinanceLog::create([
                'driver_id' => $driver->id,
                'action' => DriverFinanceLog::ACTION_ADJUSTMENT,
                'user_id' => $actor?->id,
                'new_value' => json_encode([
                    'type' => $type->value,
                    'amount' => round($signed, 2),
                    'note' => $note,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return $transaction;
        });
    }

    /**
     * Undo a manual entry captured by mistake.
     *
     * Only ever a correction of a typo: a delivery payment mirrors a delivered
     * order, and any transaction already carried by an invoice is frozen.
     *
     * @throws InvalidArgumentException when the transaction is not a reversible manual entry.
     */
    public function deleteManualTransaction(DriverTransaction $transaction, ?User $actor = null): void
    {
        $type = $transaction->transaction_type instanceof DriverTransactionType
            ? $transaction->transaction_type
            : DriverTransactionType::from((string) $transaction->transaction_type);

        if (! $type->isManual()) {
            throw new InvalidArgumentException("Transaction type {$type->value} cannot be deleted manually.");
        }

        if ($transaction->isLocked()) {
            throw new InvalidArgumentException("Transaction {$transaction->id} is already settled.");
        }

        DB::transaction(function () use ($transaction, $type, $actor): void {
            DriverFinanceLog::create([
                'driver_id' => $transaction->driver_id,
                'action' => DriverFinanceLog::ACTION_ADJUSTMENT,
                'user_id' => $actor?->id,
                'old_value' => json_encode([
                    'type' => $type->value,
                    'amount' => round((float) $transaction->amount, 2),
                    'note' => $transaction->note,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $transaction->delete();
        });
    }
}
