<?php

namespace App\Services;

use App\Enums\BulkStatusEntityType;
use App\Models\User;
use App\Support\StatusTransitionPermissions;

/**
 * Answers "which status changes may this user make in bulk?".
 *
 * The single source of truth for the whole feature: the wizard asks it what to
 * offer, the eligible-item query asks it what to select, the scanner asks it
 * whether a parcel qualifies, and the executor asks it again before writing.
 * Nothing downstream re-derives a transition list of its own, so the answer the
 * screen shows and the answer the server enforces cannot drift.
 */
class StatusTransitionAccessService
{
    /**
     * Transitions the user holds a grant for, keyed by source status.
     *
     * @return array<string, array<int, string>>
     */
    public function matrix(User $user, BulkStatusEntityType $entity): array
    {
        $matrix = [];

        foreach ($entity->transitionPairs() as $pair) {
            if (! $this->allows($user, $entity, $pair['from'], $pair['to'])) {
                continue;
            }

            $matrix[$pair['from']][] = $pair['to'];
        }

        return $matrix;
    }

    public function allows(User $user, BulkStatusEntityType $entity, string $from, string $to): bool
    {
        return $user->hasPermission(StatusTransitionPermissions::name($entity, $from, $to));
    }

    /**
     * Statuses the user may move things *into*, i.e. step 2 of the wizard.
     *
     * @return array<int, string>
     */
    public function targets(User $user, BulkStatusEntityType $entity): array
    {
        $targets = [];

        foreach ($this->matrix($user, $entity) as $to) {
            $targets = array_merge($targets, $to);
        }

        return array_values(array_unique($targets));
    }

    /**
     * Statuses an item must currently be in to be movable to `$target`.
     *
     * This is what makes the list honest: picking "Livrée" narrows the parcels
     * on screen to the ones the user may actually deliver, not to every parcel
     * that is not yet delivered.
     *
     * @return array<int, string>
     */
    public function sources(User $user, BulkStatusEntityType $entity, string $target): array
    {
        $sources = [];

        foreach ($this->matrix($user, $entity) as $from => $targets) {
            if (in_array($target, $targets, true)) {
                $sources[] = $from;
            }
        }

        return $sources;
    }

    /**
     * Narrow a user-supplied source filter to what he is actually allowed to
     * see for this target. An empty or absent filter means "all of them".
     *
     * @param  array<int, string>|string|null  $requested
     * @return array<int, string>
     */
    public function resolveSources(
        User $user,
        BulkStatusEntityType $entity,
        string $target,
        array|string|null $requested,
    ): array {
        $allowed = $this->sources($user, $entity, $target);

        if ($requested === null || $requested === '' || $requested === []) {
            return $allowed;
        }

        return array_values(array_intersect($allowed, (array) $requested));
    }

    /**
     * Entity types the user may run a bulk edit on at all.
     *
     * @return array<int, BulkStatusEntityType>
     */
    public function entities(User $user): array
    {
        return array_values(array_filter(
            BulkStatusEntityType::cases(),
            fn (BulkStatusEntityType $entity) => $this->targets($user, $entity) !== []
        ));
    }

    public function canUse(User $user, ?BulkStatusEntityType $entity = null): bool
    {
        if ($entity !== null) {
            return $this->targets($user, $entity) !== [];
        }

        return $this->entities($user) !== [];
    }

    /**
     * Everything the wizard needs to render steps 1 and 2, with the source
     * statuses of step 3 attached to each target so choosing one does not cost
     * a round trip.
     *
     * @return array<int, array<string, mixed>>
     */
    public function payload(User $user): array
    {
        return array_map(function (BulkStatusEntityType $entity) use ($user): array {
            $targets = array_map(
                fn (string $target): array => array_merge(
                    $entity->statusDescriptor($target),
                    [
                        'sources' => array_map(
                            fn (string $source): array => $entity->statusDescriptor($source),
                            $this->sources($user, $entity, $target)
                        ),
                    ]
                ),
                $this->targets($user, $entity)
            );

            return [
                'value' => $entity->value,
                'label' => $entity->label(),
                'icon' => $entity === BulkStatusEntityType::ORDER ? 'ri-shopping-bag-3-line' : 'ri-arrow-go-back-line',
                'targets' => $targets,
            ];
        }, $this->entities($user));
    }
}
