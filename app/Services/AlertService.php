<?php

namespace App\Services;

use App\Enums\AlertFormat;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Decides which announcements a given user should see right now.
 *
 * This runs on every authenticated request, so it is written to do as little
 * as possible: one indexed query over a small table, and the expensive part —
 * working out which cities a user belongs to — only when an alert actually
 * narrows its audience by city.
 */
class AlertService
{
    /**
     * Alerts the user has closed. Kept in the session rather than in the
     * database on purpose: closing a banner hides it for the rest of the
     * session, and signing in again brings the announcement back.
     */
    public const SESSION_KEY = 'alerts.dismissed';

    public function __construct(private readonly Session $session) {}

    /**
     * The alerts to render, split by where they belong on screen.
     *
     * @return array{banners: array<int, array<string, mixed>>, modals: array<int, array<string, mixed>>}
     */
    public function forUser(User $user): array
    {
        $visible = $this->visibleTo($user)->reject(
            fn (Alert $alert) => $alert->canBeDismissed() && $this->wasDismissed($alert->id)
        );

        return [
            'banners' => $this->payload($visible->filter->isBanner()),
            'modals' => $this->payload($visible->reject->isBanner()),
        ];
    }

    /**
     * Every on-air alert addressed to this user, dismissals included.
     *
     * @return Collection<int, Alert>
     */
    public function visibleTo(User $user): Collection
    {
        $onAir = Alert::query()->onAir()->get();

        if ($onAir->isEmpty()) {
            return $onAir;
        }

        $roleNames = $user->roleNames();

        // Resolving a user's cities costs up to three queries, so it is only
        // worth doing when some alert is actually filtering on them.
        $cityIds = $onAir->contains(fn (Alert $alert) => $this->narrowsByCity($alert))
            ? $user->cityIds()
            : [];

        return $onAir->filter(
            fn (Alert $alert) => $alert->targets($user->id, $roleNames, $cityIds)
        )->values();
    }

    /**
     * Records that the user closed an alert for the rest of their session.
     */
    public function dismiss(int $alertId): void
    {
        $dismissed = $this->dismissedIds();
        $dismissed[] = $alertId;

        $this->session->put(self::SESSION_KEY, array_values(array_unique($dismissed)));
    }

    public function wasDismissed(int $alertId): bool
    {
        return in_array($alertId, $this->dismissedIds(), true);
    }

    /**
     * @return array<int, int>
     */
    private function dismissedIds(): array
    {
        return array_map('intval', (array) $this->session->get(self::SESSION_KEY, []));
    }

    private function narrowsByCity(Alert $alert): bool
    {
        return ! in_array(Alert::EVERYONE, $alert->target_cities ?? [], true);
    }

    /**
     * @param  Collection<int, Alert>  $alerts
     * @return array<int, array<string, mixed>>
     */
    private function payload(Collection $alerts): array
    {
        return $alerts->map(fn (Alert $alert) => [
            'id' => $alert->id,
            'title' => $alert->title,
            'message' => $alert->message,
            'type' => $alert->type->value,
            'icon' => $alert->type->icon(),
            'format' => $alert->display_format->value,
            'is_dismissible' => $alert->canBeDismissed(),
        ])->values()->all();
    }

    /**
     * @return array<int, string>
     */
    public static function formats(): array
    {
        return AlertFormat::values();
    }
}
