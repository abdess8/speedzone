<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\NotificationPermissions;

class NotificationPreferenceService
{
    /**
     * @return array<string, bool>
     */
    public function defaults(): array
    {
        $defaults = ['enabled' => true];

        foreach (NotificationType::cases() as $type) {
            $defaults[$type->value] = true;
        }

        return $defaults;
    }

    public function forUser(User $user): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            $this->defaults(),
        );
    }

    /**
     * Whether a notification of this type should reach the user at all.
     *
     * Entitlement first: a topic that does not concern the user's role is not
     * something he can opt back into from the settings screen.
     */
    public function isEnabled(User $user, NotificationType $type): bool
    {
        if (! $this->isEntitledTo($user, $type)) {
            return false;
        }

        return $this->forUser($user)->isTypeEnabled($type);
    }

    public function isEntitledTo(User $user, NotificationType $type): bool
    {
        return $user->hasPermission(NotificationPermissions::for($type));
    }

    /**
     * The topics this user may receive, in enum order.
     *
     * @return array<int, NotificationType>
     */
    public function availableTypes(User $user): array
    {
        return array_values(array_filter(
            NotificationType::cases(),
            fn (NotificationType $type) => $this->isEntitledTo($user, $type)
        ));
    }

    /**
     * @param  array<string, bool>  $data
     */
    public function update(User $user, array $data): NotificationPreference
    {
        $preference = $this->forUser($user);

        $payload = collect($data)->only($this->editableKeys($user))->all();

        $preference->update($payload);

        return $preference->fresh();
    }

    /**
     * Preference keys the user is allowed to send back.
     *
     * @return array<int, string>
     */
    public function editableKeys(User $user): array
    {
        return [
            'enabled',
            ...array_map(
                static fn (NotificationType $type) => $type->value,
                $this->availableTypes($user)
            ),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(User $user): array
    {
        return $this->forUser($user)->only($this->editableKeys($user));
    }
}
