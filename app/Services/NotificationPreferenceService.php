<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;

class NotificationPreferenceService
{
    /**
     * @return array<string, bool>
     */
    public function defaults(): array
    {
        return [
            'enabled' => true,
            'invoice_generated' => true,
            'ticket_created' => true,
            'ticket_message' => true,
            'ticket_closed' => true,
            'return_requested' => true,
            'system_notifications' => true,
        ];
    }

    public function forUser(User $user): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            $this->defaults(),
        );
    }

    public function isEnabled(User $user, NotificationType $type): bool
    {
        return $this->forUser($user)->isTypeEnabled($type);
    }

    /**
     * @param  array<string, bool>  $data
     */
    public function update(User $user, array $data): NotificationPreference
    {
        $preference = $this->forUser($user);

        $allowed = array_keys($this->defaults());
        $payload = collect($data)->only($allowed)->all();

        $preference->update($payload);

        return $preference->fresh();
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(User $user): array
    {
        return $this->forUser($user)->only(array_keys($this->defaults()));
    }
}
