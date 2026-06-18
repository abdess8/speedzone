<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts partner delivery queries to records the authenticated user may access.
 *
 * - Drivers: only deliveries assigned to them (driver_id).
 * - Admins: only deliveries for partners linked via partner_user.
 * - Users with partners.read ("gérer tous les partenaires"): no restriction.
 */
class PartnerDeliveryVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            $builder->whereRaw('1 = 0');

            return;
        }

        self::applyToQuery($builder, $user);
    }

    public static function applyToQuery(Builder $query, User $user): Builder
    {
        $query->whereNotNull('partner_id');

        if ($user->canManageAllPartners()) {
            return $query;
        }

        if ($user->isDriver()) {
            return $query->where('driver_id', $user->id);
        }

        return $query->whereIn('partner_id', $user->partners()->select('partners.id'));
    }
}
