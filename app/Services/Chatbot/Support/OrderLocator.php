<?php

namespace App\Services\Chatbot\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Turns whatever the user typed ("#1024", "SPD-2026-000045", "spd 2026 45")
 * into an Order the acting user is actually allowed to see.
 *
 * An order the user may not read resolves to null rather than to a denial, so
 * the assistant can never be used to probe which tracking numbers exist.
 */
class OrderLocator
{
    public function find(string $reference, User $user): ?Order
    {
        $order = $this->query($this->normalise($reference));

        if (! $order || ! Gate::forUser($user)->allows('view', $order)) {
            return null;
        }

        return $order;
    }

    private function normalise(string $reference): string
    {
        return Str::of($reference)
            ->trim()
            ->ltrim('#')
            ->replace([' ', '_'], '-')
            ->upper()
            ->squish()
            ->value();
    }

    private function query(string $reference): ?Order
    {
        if ($reference === '') {
            return null;
        }

        $query = Order::query()->with(['city', 'seller', 'driver', 'invoice']);

        // A bare number is ambiguous: it is far more often the tail of a
        // tracking number than a primary key, so both are tried.
        if (ctype_digit($reference)) {
            return $query
                ->where(fn ($q) => $q
                    ->where('id', (int) $reference)
                    ->orWhere('tracking_number', 'like', '%-'.$reference))
                ->orderByRaw('id = ? DESC', [(int) $reference])
                ->first();
        }

        return $query
            ->where('tracking_number', $reference)
            ->orWhere('external_tracking_code', $reference)
            ->first();
    }

    /**
     * Compact order view shared by every tool and by the widget.
     *
     * The link is resolved here rather than in Vue: only the policy knows
     * whether this user may open the full detail screen, and a widget that
     * guessed would hand out buttons leading to a 403.
     *
     * @return array<string, mixed>
     */
    public static function summarise(Order $order, ?User $viewer = null): array
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from((string) $order->status);

        return [
            'id' => $order->id,
            'url' => self::urlFor($order, $viewer),
            'tracking_number' => $order->tracking_number,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'status_icon' => $status->icon(),
            'customer_name' => $order->customer_full_name,
            'customer_phone' => $order->customer_phone,
            'city' => $order->city?->name,
            'driver_name' => $order->driver?->name,
            'seller_name' => $order->seller?->name,
            'total_amount' => (float) $order->total_amount,
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    private static function urlFor(Order $order, ?User $viewer): string
    {
        if ($viewer && Gate::forUser($viewer)->allows('viewDetails', $order)) {
            return route('orders.show', $order->id);
        }

        // Everyone else still gets the tracking timeline, which is the screen a
        // field agent works from anyway.
        return route('orders.track', $order->tracking_number);
    }
}
