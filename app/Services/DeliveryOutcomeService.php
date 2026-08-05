<?php

namespace App\Services;

use App\Enums\DeliveryOutcome;
use App\Enums\OrderFailureReason;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes a delivery attempt.
 *
 * A driver standing at the door reports one of two things: he handed the parcel
 * over, or he did not. Only the second case needs a decision, and that decision
 * belongs to the failure reason rather than to the driver:
 *
 *  - refused or cancelled — the customer's final word. The parcel leaves the
 *    round and waits for the reverse leg on READY_TO_RETURN.
 *  - anything else — absent, unreachable, wrong address. Nothing is settled, so
 *    the order stays OUT_FOR_DELIVERY, the attempt is counted, and the driver
 *    may come back tomorrow.
 *
 * Both branches leave a timeline entry, optionally carrying the photo or
 * document the driver attached as proof.
 */
class DeliveryOutcomeService
{
    private const ATTACHMENT_DISK = 'public';

    private const ATTACHMENT_FOLDER = 'orders/delivery-attempts';

    public function __construct(private readonly OrderTransitionService $transitions) {}

    /**
     * Statuses a delivery outcome may be reported from.
     *
     * @return array<int, string>
     */
    public static function reportableStatuses(): array
    {
        return [OrderStatus::OUT_FOR_DELIVERY->value];
    }

    public static function isReportable(Order $order): bool
    {
        $status = $order->status instanceof OrderStatus ? $order->status->value : $order->status;

        return in_array($status, self::reportableStatuses(), true);
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function record(
        Order $order,
        User $actor,
        DeliveryOutcome $outcome,
        ?OrderFailureReason $reason = null,
        ?string $note = null,
        ?UploadedFile $attachment = null,
    ): Order {
        if (! self::isReportable($order)) {
            throw ValidationException::withMessages([
                'outcome' => __('orders.delivery_outcome.not_out_for_delivery'),
            ]);
        }

        if ($outcome === DeliveryOutcome::DELIVERED) {
            return $this->transitions->transition(
                $order,
                OrderStatus::DELIVERED->value,
                $actor,
                $note,
                $this->storeAttachment($order, $attachment),
            );
        }

        if (! $reason instanceof OrderFailureReason) {
            throw ValidationException::withMessages([
                'failure_reason' => __('orders.failure.reason_required'),
            ]);
        }

        $context = array_merge(
            [
                'failure_reason' => $reason->value,
                'failure_note' => $note,
            ],
            $this->storeAttachment($order, $attachment),
        );

        return $reason->endsDelivery()
            ? $this->transitions->transition(
                $order,
                OrderStatus::READY_TO_RETURN->value,
                $actor,
                $note,
                $context,
            )
            : $this->recordFailedAttempt($order, $actor, $reason, $note, $context);
    }

    /**
     * A delivery that can still be retried: the status does not move, but the
     * attempt is counted and timestamped so the round can be audited.
     *
     * @param  array{attachment_path?: string|null, attachment_name?: string|null}  $context
     */
    private function recordFailedAttempt(
        Order $order,
        User $actor,
        OrderFailureReason $reason,
        ?string $note,
        array $context,
    ): Order {
        return DB::transaction(function () use ($order, $actor, $reason, $note, $context): Order {
            // Two drivers never share a parcel, but a double-tap on a flaky
            // mobile connection is routine, and the counter must survive it.
            $attempt = (int) Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->value('failed_attempts_count') + 1;

            $order->update([
                'failure_reason' => $reason,
                'failure_note' => $note,
                'failed_at' => now(),
                'failed_attempts_count' => $attempt,
            ]);

            // Stamped against the status the order keeps, so the timeline reads
            // as a list of attempts under a single delivery leg.
            $order->recordStatus(
                OrderStatus::OUT_FOR_DELIVERY,
                $actor,
                $this->attemptComment($attempt, $reason, $note),
                attachmentPath: $context['attachment_path'] ?? null,
                attachmentName: $context['attachment_name'] ?? null,
            );

            return $order->refresh();
        });
    }

    private function attemptComment(int $attempt, OrderFailureReason $reason, ?string $note): string
    {
        return collect([
            __('orders.delivery_outcome.attempt_label', ['count' => $attempt]),
            $reason->label(),
            $note,
        ])->filter(fn (?string $part) => filled($part))->implode(' — ');
    }

    /**
     * @return array{attachment_path?: string, attachment_name?: string}
     */
    private function storeAttachment(Order $order, ?UploadedFile $attachment): array
    {
        if (! $attachment) {
            return [];
        }

        return [
            'attachment_path' => $attachment->store(
                self::ATTACHMENT_FOLDER.'/'.$order->id,
                self::ATTACHMENT_DISK
            ),
            'attachment_name' => $attachment->getClientOriginalName(),
        ];
    }
}
