<?php

namespace App\Support;

/**
 * Normalizes notification payloads for display — supports both the new
 * standardized format and legacy support-ticket notifications.
 */
final class NotificationPresenter
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, title: string, message: string}
     */
    public static function display(array $data): array
    {
        if (! empty($data['title']) && ! empty($data['message'])) {
            return [
                'type' => self::normalizeType((string) ($data['type'] ?? '')),
                'title' => (string) $data['title'],
                'message' => (string) $data['message'],
            ];
        }

        return match ($data['type'] ?? '') {
            'support.ticket.created' => [
                'type' => 'ticket_created',
                'title' => trans('notifications.titles.ticket_created'),
                'message' => isset($data['subject'])
                    ? trans('notifications.messages.ticket_created_with_subject', [
                        'reference' => $data['reference'] ?? '',
                        'subject' => $data['subject'],
                    ])
                    : trans('notifications.messages.ticket_created', [
                        'reference' => $data['reference'] ?? '',
                        'seller' => trans('notifications.unknown_user'),
                    ]),
            ],
            'support.ticket.reply' => [
                'type' => 'ticket_message',
                'title' => trans('notifications.titles.ticket_message'),
                'message' => trans('notifications.messages.ticket_message', [
                    'reference' => $data['reference'] ?? '',
                ]),
            ],
            'support.ticket.assigned' => [
                'type' => 'system_notifications',
                'title' => trans('notifications.titles.ticket_assigned'),
                'message' => trans('notifications.messages.ticket_assigned', [
                    'reference' => $data['reference'] ?? '',
                ]),
            ],
            default => [
                'type' => self::normalizeType((string) ($data['type'] ?? 'system_notifications')),
                'title' => (string) ($data['subject'] ?? $data['reference'] ?? trans('notifications.titles.ticket_created')),
                'message' => (string) ($data['reference'] ?? $data['subject'] ?? ''),
            ],
        };
    }

    private static function normalizeType(string $type): string
    {
        return match ($type) {
            'support.ticket.created' => 'ticket_created',
            'support.ticket.reply' => 'ticket_message',
            'support.ticket.assigned' => 'system_notifications',
            default => $type,
        };
    }
}
