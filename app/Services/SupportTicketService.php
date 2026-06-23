<?php

namespace App\Services;

use App\Enums\SupportObjectType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Events\TicketClosed;
use App\Events\TicketCreated;
use App\Events\TicketMessageCreated;
use App\Notifications\SupportTicketAssignedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SupportTicketService
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    private const ATTACHMENT_DISK = 'public';

    private const ATTACHMENT_FOLDER = 'support-tickets';

    /**
     * Open a new ticket for a seller, optionally linked to an operational object.
     *
     * @param  array{category: string, subject: string, message: string, object_type?: ?string, object_id?: ?int}  $data
     */
    public function createTicket(User $author, array $data, ?UploadedFile $attachment = null): SupportTicket
    {
        return DB::transaction(function () use ($author, $data, $attachment) {
            $objectType = ! empty($data['object_type'])
                ? SupportObjectType::tryFrom($data['object_type'])
                : null;

            $ticket = SupportTicket::create([
                'reference' => 'PENDING',
                'created_by' => $author->id,
                'object_type' => $objectType?->value,
                'object_id' => $objectType ? ($data['object_id'] ?? null) : null,
                'category' => SupportTicketCategory::from($data['category'])->value,
                'subject' => $data['subject'],
                'message' => $data['message'],
                'status' => SupportTicketStatus::OPEN->value,
            ]);

            $ticket->forceFill(['reference' => $this->makeReference($ticket)])->save();

            if ($attachment) {
                $this->storeTicketAttachment($ticket, $author, $attachment);
            }

            event(new TicketCreated($ticket));

            return $ticket;
        });
    }

    /**
     * Post a chat reply on a ticket and bump its activity / status.
     */
    public function addMessage(SupportTicket $ticket, User $sender, ?string $body, ?UploadedFile $attachment = null): SupportMessage
    {
        return DB::transaction(function () use ($ticket, $sender, $body, $attachment) {
            $stored = null;
            $name = null;

            if ($attachment) {
                $stored = $attachment->store(self::ATTACHMENT_FOLDER.'/messages', self::ATTACHMENT_DISK);
                $name = $attachment->getClientOriginalName();
            }

            $message = $ticket->messages()->create([
                'sender_id' => $sender->id,
                'message' => $body,
                'attachment' => $stored,
                'attachment_name' => $name,
            ]);

            $ticket->forceFill(['last_reply_at' => now()]);

            // Auto-advance status based on who replied (unless resolved/closed).
            $current = $ticket->statusEnum();
            if (! in_array($current, [SupportTicketStatus::RESOLVED, SupportTicketStatus::CLOSED], true)) {
                $isSeller = $ticket->created_by === $sender->id;
                $ticket->status = $isSeller
                    ? SupportTicketStatus::IN_PROGRESS
                    : SupportTicketStatus::WAITING_SELLER;
            }

            $ticket->save();

            event(new TicketMessageCreated($ticket, $message, $sender));

            return $message;
        });
    }

    /**
     * Assign (or reassign) a ticket to a support agent.
     */
    public function assign(SupportTicket $ticket, ?User $agent): SupportTicket
    {
        $ticket->assigned_to = $agent?->id;

        if ($agent && $ticket->statusEnum() === SupportTicketStatus::OPEN) {
            $ticket->status = SupportTicketStatus::IN_PROGRESS;
        }

        $ticket->save();

        if ($agent) {
            $this->dispatcher->send($agent, new SupportTicketAssignedNotification($ticket->refresh()));
        }

        return $ticket;
    }

    public function changeStatus(SupportTicket $ticket, SupportTicketStatus $status, User $actor): SupportTicket
    {
        if ($status === SupportTicketStatus::CLOSED) {
            return $this->close($ticket, $actor);
        }

        $ticket->status = $status->value;
        $ticket->save();

        return $ticket;
    }

    public function close(SupportTicket $ticket, User $actor): SupportTicket
    {
        $ticket->forceFill([
            'status' => SupportTicketStatus::CLOSED->value,
            'closed_at' => now(),
            'closed_by' => $actor->id,
        ])->save();

        event(new TicketClosed($ticket->fresh(), $actor));

        return $ticket;
    }

    private function storeTicketAttachment(SupportTicket $ticket, User $author, UploadedFile $file): void
    {
        $path = $file->store(self::ATTACHMENT_FOLDER.'/attachments', self::ATTACHMENT_DISK);

        $ticket->attachments()->create([
            'uploaded_by' => $author->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
        ]);
    }

    private function makeReference(SupportTicket $ticket): string
    {
        return sprintf('SUP-%d-%05d', ($ticket->created_at ?? now())->year, $ticket->id);
    }

}
