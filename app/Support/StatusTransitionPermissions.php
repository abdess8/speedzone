<?php

namespace App\Support;

use App\Enums\BulkStatusEntityType;
use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;

/**
 * Naming and cataloguing of the `source status → target status` grants that
 * govern bulk status editing.
 *
 * These are a *second* gate, not a replacement for the existing
 * `orders.transition.to_*` / `returns.transition.to_*` grants: a batch still
 * goes through OrderTransitionService and ReturnTransitionService, which check
 * those. What this adds is the ability to say "this role may close a delivery
 * that is already out on the round, but not one that has merely reached the
 * city" — a distinction the target-only grants cannot express, and the whole
 * reason the bulk screen needs its own permission axis.
 *
 * The pairs are derived from the transition graphs rather than listed here, so
 * a new edge in a graph cannot be silently unmanageable.
 */
final class StatusTransitionPermissions
{
    /**
     * Marks these rows in the `permissions` table so the admin matrix can find
     * them and the Roles screen can render them as transitions rather than as
     * yet another flat checkbox.
     */
    public const TYPE = 'status_transition';

    public const ACTION = 'status_transition';

    /**
     * Canonical permission name for one transition.
     *
     * `orders.status_transition.in_delivery_city.delivered`
     */
    public static function name(BulkStatusEntityType $entity, string $from, string $to): string
    {
        return implode('.', [
            $entity->resource(),
            self::ACTION,
            strtolower($from),
            strtolower($to),
        ]);
    }

    /**
     * Every manageable transition, across both entities.
     *
     * @return array<int, array{entity: BulkStatusEntityType, from: string, to: string, name: string}>
     */
    public static function pairs(): array
    {
        $pairs = [];

        foreach (BulkStatusEntityType::cases() as $entity) {
            foreach ($entity->transitionPairs() as $pair) {
                $pairs[] = [
                    'entity' => $entity,
                    'from' => $pair['from'],
                    'to' => $pair['to'],
                    'name' => self::name($entity, $pair['from'], $pair['to']),
                ];
            }
        }

        return $pairs;
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::pairs(), 'name');
    }

    /**
     * Rows for {@see PermissionCatalog}, shaped like every other entry so the
     * seeder, the export command and the Roles screen need no special case.
     *
     * @return array<int, array<string, string|null>>
     */
    public static function catalog(): array
    {
        return array_map(
            static fn (array $pair): array => [
                'name' => $pair['name'],
                'resource' => $pair['entity']->resource(),
                'action' => self::ACTION,
                'scope' => null,
                'type' => self::TYPE,
                'description' => null,
            ],
            self::pairs()
        );
    }

    /**
     * The transitions a role should hold given the target-status grants it
     * already has.
     *
     * Bulk editing must not widen anybody's reach on the day it ships: a role
     * that could already stamp a status one order at a time gets to stamp it a
     * hundred at a time, and a role that could not, still cannot. From there
     * the administrator narrows or widens the matrix by hand.
     *
     * @param  array<int, string>  $granted  Permission names the role holds.
     * @return array<int, string>
     */
    public static function derivedFrom(array $granted): array
    {
        $held = array_flip($granted);
        $names = [];

        foreach (self::pairs() as $pair) {
            if (self::grantsTarget($pair['entity'], $pair['to'], $held)) {
                $names[] = $pair['name'];
            }
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $held  Permission names as keys.
     */
    private static function grantsTarget(BulkStatusEntityType $entity, string $to, array $held): bool
    {
        $required = match ($entity) {
            BulkStatusEntityType::ORDER => array_filter([OrderStatus::from($to)->transitionPermission()]),
            BulkStatusEntityType::RETURN => array_merge(ReturnStatus::from($to)->allowedBy(), ['returns.manage']),
        };

        foreach ($required as $permission) {
            if (isset($held[$permission])) {
                return true;
            }
        }

        return false;
    }
}
