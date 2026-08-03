<?php

namespace Database\Seeders\Support;

use App\Enums\SupportObjectType;
use App\Enums\SupportTicketStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Generates seller claims: a ticket, the conversation it triggered (2 to 4
 * replies between the seller and the support desk) and its attachments.
 *
 * Every ticket points at a real row the seller owns — an order, an invoice or a
 * pickup request — and its wording quotes that row (tracking number, reference,
 * amount, city), so the claim reads like a genuine one rather than lorem ipsum.
 */
class SupportTicketGenerator
{
    /** @var array<int, string> */
    private array $sellerFollowUpsLatin = [
        'Bonjour, avez-vous du nouveau sur ce dossier ? Le client me relance tous les jours.',
        'Relance : toujours sans réponse de votre part, merci de traiter rapidement.',
        'Je reste à votre disposition si vous avez besoin d\'informations supplémentaires.',
    ];

    /** @var array<int, string> */
    private array $sellerFollowUpsArabic = [
        'السلام عليكم، هل هناك أي تحديث في هذا الملف؟ الزبون يتصل بي كل يوم.',
        'تذكير: لم أتلق أي جواب بعد، المرجو معالجة الملف بسرعة.',
    ];

    /** @var array<int, string> */
    private array $supportClosingsLatin = [
        'Dossier traité de notre côté. Nous restons disponibles si le problème réapparaît.',
        'Nous clôturons la réclamation, la correction est visible dans votre espace.',
    ];

    /** @var array<int, string> */
    private array $supportClosingsArabic = [
        'تمت معالجة الملف من جهتنا. نبقى في خدمتكم إذا تكرر المشكل.',
        'تم إغلاق الشكاية، التصحيح ظاهر في حسابكم.',
    ];

    public function __construct(private readonly DatasetContext $ctx) {}

    public function run(int $count): void
    {
        $statuses = $this->statusPlan($count);

        for ($index = 0; $index < $count; $index++) {
            // Exactly one ticket in five is written in Arabic.
            $arabic = $index % 5 === 0;

            $template = $this->ctx->faker->pick(
                $arabic ? SupportTicketTemplates::arabic() : SupportTicketTemplates::latin()
            );

            $this->createTicket($template, $statuses[$index], $arabic);
        }
    }

    /**
     * @return array<int, SupportTicketStatus>
     */
    private function statusPlan(int $count): array
    {
        $weights = [
            SupportTicketStatus::OPEN->value => 4,
            SupportTicketStatus::IN_PROGRESS->value => 8,
            SupportTicketStatus::WAITING_SELLER->value => 6,
            SupportTicketStatus::RESOLVED->value => 7,
            SupportTicketStatus::CLOSED->value => 5,
        ];

        $plan = [];
        foreach ($weights as $status => $weight) {
            $plan = array_merge($plan, array_fill(0, $weight, SupportTicketStatus::from($status)));
        }

        while (count($plan) < $count) {
            $plan[] = SupportTicketStatus::IN_PROGRESS;
        }

        $plan = array_slice($plan, 0, $count);
        shuffle($plan);

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private function createTicket(array $template, SupportTicketStatus $status, bool $arabic): void
    {
        $seller = $this->ctx->sellers->random();
        [$objectType, $object] = $this->resolveObject($seller, $template['object']);

        if (! $object) {
            return;
        }

        $agent = $this->ctx->agent();
        $openedAt = $this->ctx->after($object->created_at, 3, 96) ?? $this->ctx->moment();
        $replacements = $this->replacements($object);

        $ticket = new SupportTicket([
            'reference' => 'PENDING',
            'created_by' => $seller->id,
            'store_id' => $this->ctx->store($seller)?->id,
            'assigned_to' => $status === SupportTicketStatus::OPEN ? null : $agent->id,
            'object_type' => $objectType?->value,
            'object_id' => $object->getKey(),
            'category' => $template['category']->value,
            'subject' => strtr($template['subject'], $replacements),
            'message' => strtr($template['message'], $replacements),
            'status' => $status->value,
        ]);
        $this->ctx->saveAt($ticket, $openedAt);
        $ticket->forceFill([
            'reference' => sprintf('SUP-%d-%05d', $openedAt->year, $ticket->id),
        ])->save();
        $this->ctx->bump('tickets');

        if ($arabic) {
            $this->ctx->bump('arabic_tickets');
        }

        foreach ($template['attachments'] as $fileName) {
            $this->ctx->saveAt($ticket->attachments()->make([
                'uploaded_by' => $seller->id,
                'file_path' => $this->ctx->storeFile('support-tickets/attachments', $fileName),
                'file_name' => $fileName,
            ]), $openedAt);

            $this->ctx->bump('ticket_attachments');
        }

        $lastReplyAt = $this->seedThread($ticket, $seller, $agent, $template, $status, $arabic, $openedAt, $replacements);

        if ($status === SupportTicketStatus::CLOSED) {
            $closedAt = $this->ctx->clamp($lastReplyAt->copy()->addHours(random_int(1, 20)));

            $this->ctx->updateAt($ticket, [
                'last_reply_at' => $lastReplyAt,
                'closed_at' => $closedAt,
                'closed_by' => $agent->id,
            ], $closedAt);

            return;
        }

        $this->ctx->updateAt($ticket, ['last_reply_at' => $lastReplyAt], $lastReplyAt);
    }

    /**
     * Post the conversation and return the moment of the last message.
     *
     * The number of replies is chosen so the last speaker matches the ticket
     * status: the seller spoke last on an in-progress ticket, the support desk
     * spoke last on a ticket waiting for the seller, resolved or closed.
     *
     * @param  array<string, mixed>  $template
     * @param  array<string, string>  $replacements
     */
    private function seedThread(
        SupportTicket $ticket,
        User $seller,
        User $agent,
        array $template,
        SupportTicketStatus $status,
        bool $arabic,
        Carbon $openedAt,
        array $replacements,
    ): Carbon {
        $replies = $this->buildReplies($template, $status, $arabic);
        $cursor = $openedAt;

        foreach ($replies as $reply) {
            $cursor = $this->ctx->after($cursor, 1, 30) ?? $cursor->copy()->addMinutes(random_int(20, 180));
            $cursor = $this->ctx->clamp($cursor);

            $sender = $reply['from'] === 'seller' ? $seller : $agent;
            $attachment = $reply['attachment'] ?? null;

            $this->ctx->saveAt($ticket->messages()->make([
                'sender_id' => $sender->id,
                'message' => strtr($reply['body'], $replacements),
                'attachment' => $attachment ? $this->ctx->storeFile('support-tickets/messages', $attachment) : null,
                'attachment_name' => $attachment,
            ]), $cursor);

            $this->ctx->bump('ticket_messages');
        }

        return $cursor;
    }

    /**
     * Templates alternate support → seller → support → seller, so an odd number
     * of replies ends on the support desk and an even one ends on the seller.
     *
     * @param  array<string, mixed>  $template
     * @return array<int, array{from: string, body: string, attachment?: ?string}>
     */
    private function buildReplies(array $template, SupportTicketStatus $status, bool $arabic): array
    {
        /** @var array<int, array{from: string, body: string, attachment?: ?string}> $available */
        $available = $template['replies'];

        // An open ticket has not been answered yet: only the seller talks.
        if ($status === SupportTicketStatus::OPEN) {
            $followUps = $arabic ? $this->sellerFollowUpsArabic : $this->sellerFollowUpsLatin;

            return array_map(
                static fn (string $body) => ['from' => 'seller', 'body' => $body],
                array_slice($followUps, 0, random_int(1, 2))
            );
        }

        $supportLast = in_array($status, [
            SupportTicketStatus::WAITING_SELLER,
            SupportTicketStatus::RESOLVED,
            SupportTicketStatus::CLOSED,
        ], true);

        $wanted = $supportLast ? $this->ctx->faker->pick([3, 3, 5]) : $this->ctx->faker->pick([2, 4]);
        $count = min($wanted, count($available));

        // Keep the parity: drop the trailing reply when it belongs to the wrong side.
        if ($supportLast && $count % 2 === 0) {
            $count--;
        }
        if (! $supportLast && $count % 2 !== 0) {
            $count--;
        }

        $replies = array_slice($available, 0, max($count, 0));

        // Two messages minimum per thread, and the right speaker at the end.
        if (count($replies) < 2) {
            $replies = array_slice($available, 0, min(2, count($available)));
        }

        $lastFrom = $replies === [] ? 'seller' : $replies[count($replies) - 1]['from'];

        if ($supportLast && $lastFrom !== 'support') {
            $replies[] = [
                'from' => 'support',
                'body' => $this->ctx->faker->pick($arabic ? $this->supportClosingsArabic : $this->supportClosingsLatin),
            ];
        }

        if (! $supportLast && $lastFrom !== 'seller') {
            $replies[] = [
                'from' => 'seller',
                'body' => $this->ctx->faker->pick($arabic ? $this->sellerFollowUpsArabic : $this->sellerFollowUpsLatin),
            ];
        }

        return $replies;
    }

    /**
     * Pick a real row of the seller for the claim to point at.
     *
     * @return array{0: ?SupportObjectType, 1: ?Model}
     */
    private function resolveObject(User $seller, ?SupportObjectType $type): array
    {
        $order = fn () => $this->ctx->ordersOf($seller)
            ->with(['city', 'sector'])
            ->inRandomOrder()
            ->first();

        return match ($type) {
            SupportObjectType::INVOICE => (function () use ($seller, $order) {
                $invoice = Invoice::acrossStores()->where('seller_id', $seller->id)->inRandomOrder()->first();

                return $invoice
                    ? [SupportObjectType::INVOICE, $invoice]
                    : [SupportObjectType::ORDER, $order()];
            })(),
            SupportObjectType::PICKUP_REQUEST => (function () use ($seller, $order) {
                $pickup = PickupRequest::acrossStores()->where('created_by', $seller->id)->inRandomOrder()->first();

                return $pickup
                    ? [SupportObjectType::PICKUP_REQUEST, $pickup]
                    : [SupportObjectType::ORDER, $order()];
            })(),
            default => [SupportObjectType::ORDER, $order()],
        };
    }

    /**
     * @return array<string, string>
     */
    private function replacements(Model $object): array
    {
        if ($object instanceof Order) {
            return [
                '{tracking}' => (string) $object->tracking_number,
                '{city}' => (string) ($object->city?->name ?? 'Casablanca'),
                '{customer}' => $object->customer_full_name,
                '{reference}' => (string) $object->tracking_number,
                '{amount}' => number_format((float) ($object->order_amount ?? $object->order_value ?? 0), 2, ',', ' '),
            ];
        }

        if ($object instanceof Invoice) {
            $line = $object->orders()->with(['city'])->first();

            return [
                '{tracking}' => (string) ($line?->tracking_number ?? '—'),
                '{city}' => (string) ($line?->city?->name ?? 'Casablanca'),
                '{customer}' => $line?->customer_full_name ?? '—',
                '{reference}' => (string) $object->invoice_number,
                '{amount}' => number_format((float) $object->net_amount, 2, ',', ' '),
            ];
        }

        /** @var PickupRequest $object */
        $line = $object->orders()->with('city')->first();

        return [
            '{tracking}' => (string) ($line?->tracking_number ?? '—'),
            '{city}' => (string) ($line?->city?->name ?? 'Casablanca'),
            '{customer}' => $line?->customer_full_name ?? '—',
            '{reference}' => (string) $object->reference,
            '{amount}' => number_format((float) $object->total_orders_amount, 2, ',', ' '),
        ];
    }
}
