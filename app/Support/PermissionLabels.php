<?php

namespace App\Support;

use App\Enums\BulkStatusEntityType;
use App\Models\Permission;
use Illuminate\Support\Str;

/**
 * Turns a permission row into something an administrator can read.
 *
 * A permission name is written for the code that checks it — "orders.read.own",
 * "driver_invoices.assign_driver" — and an administrator ticking boxes on the
 * roles screen should never have to decode one. Every string therefore comes
 * from permission_catalog.php, which holds a headline and a help text per
 * permission in each locale.
 *
 * That group is deliberately absent from the frontend translation bundle: it is
 * tens of kilobytes and would ride along with every page, so the roles screen
 * receives the resolved strings as plain props instead.
 *
 * The status-change grants are the exception. There are fifty-odd of them and
 * they are generated from the status graph, so they are labelled from the same
 * enums the tracking screens use rather than written out one by one — the
 * vocabulary stays in step with the rest of the product for free.
 */
final class PermissionLabels
{
    private const GROUP = 'permission_catalog';

    /**
     * Scopes worth a pill next to the label.
     *
     * Notification grants store their topic in the scope column, which is
     * already spelled out in the headline; showing "invoice_generated" beside
     * "Be told when an invoice is issued" would only add noise.
     *
     * @var array<int, string>
     */
    private const LABELLED_SCOPES = ['own', 'all', 'assigned'];

    public static function resourceLabel(string $resource): string
    {
        return self::entry('resources', $resource)
            ?? Str::headline(str_replace('_', ' ', $resource));
    }

    public static function permissionLabel(Permission $permission): string
    {
        $label = self::entry('names', $permission->name);

        if ($label !== null) {
            return $label;
        }

        return self::transitionText($permission, 'label')
            ?? self::fallbackLabel($permission);
    }

    public static function permissionDescription(Permission $permission): ?string
    {
        return self::entry('descriptions', $permission->name)
            ?? self::transitionText($permission, 'description');
    }

    /**
     * The pill shown beside the label, or null when the scope is not one of
     * the three reaches an administrator reasons about.
     */
    public static function scopeLabel(?string $scope): ?string
    {
        if ($scope === null || ! in_array($scope, self::LABELLED_SCOPES, true)) {
            return null;
        }

        return self::entry('scopes', $scope) ?? $scope;
    }

    /**
     * Labels and help texts for the two families of status grants, built from
     * the status enums so they read like the parcel screens.
     */
    private static function transitionText(Permission $permission, string $key): ?string
    {
        $entity = $permission->resource === 'returns'
            ? BulkStatusEntityType::RETURN
            : BulkStatusEntityType::ORDER;

        if ($permission->type === 'workflow_transition') {
            $target = Str::of($permission->name)->afterLast('to_')->upper()->value();

            return self::translate(
                "transitions.workflow.{$entity->resource()}.{$key}",
                ['status' => $entity->statusLabel($target)]
            );
        }

        if ($permission->type !== StatusTransitionPermissions::TYPE) {
            return null;
        }

        // A bulk-edit grant names both ends, and both have to show: "→ Livré"
        // would be indistinguishable from the workflow grant above, while the
        // whole point of these is the route taken to get there.
        $pair = Str::of($permission->name)
            ->after('.'.StatusTransitionPermissions::ACTION.'.')
            ->explode('.');

        if ($pair->count() !== 2) {
            return null;
        }

        return self::translate("transitions.pair.{$entity->resource()}.{$key}", [
            'from' => $entity->statusLabel(strtoupper($pair[0])),
            'to' => $entity->statusLabel(strtoupper($pair[1])),
        ]);
    }

    /**
     * Last resort for a permission the catalogue has not been taught yet. It
     * reads poorly on purpose — a permission reaching this branch is one
     * PermissionLabelCoverageTest is meant to catch before a release does.
     */
    private static function fallbackLabel(Permission $permission): string
    {
        $text = Str::headline(str_replace('_', ' ', $permission->action));

        if ($permission->scope) {
            $text .= ' ('.Str::title($permission->scope).')';
        }

        return $text;
    }

    /**
     * A permission name contains dots, and Laravel reads a dotted translation
     * key as a path through nested arrays: "names.orders.read.own" would look
     * for a 'read' array inside an 'orders' array. The section is therefore
     * fetched whole and indexed by hand, so "orders.read.own" stays one key.
     */
    private static function entry(string $section, string $key): ?string
    {
        $lines = trans(self::GROUP.'.'.$section);

        return is_array($lines) && isset($lines[$key]) && is_string($lines[$key])
            ? $lines[$key]
            : null;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function translate(string $key, array $replace = []): ?string
    {
        $full = self::GROUP.'.'.$key;
        $value = __($full, $replace);

        return is_string($value) && $value !== $full ? $value : null;
    }
}
