<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Support\StockPermissions;
use App\Support\StoreContext;

/**
 * Catalog access.
 *
 * RBAC decides what the actor may do; the row-level half is mostly handled for
 * free by the `store` global scope, which makes another vendor's product simply
 * not exist for this request. The explicit ownership checks below cover the one
 * case the scope cannot: staff accounts, which are bound to no store and would
 * otherwise be able to edit a vendor's sheet.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(StockPermissions::VIEW)
            || $user->hasPermission(StockPermissions::RECEIVE_INBOUND)
            || $user->hasPermission(StockPermissions::ADMIN_OVERRIDE);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    /**
     * A product cannot exist outside a shop, so creating one requires standing
     * on a store. That excludes staff accounts, which are bound to none — a hub
     * agent counts a vendor's goods, he does not open references for him.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(StockPermissions::CREATE_PRODUCT)
            && app(StoreContext::class)->isEnforced();
    }

    /**
     * Editing the product sheet.
     *
     * Reserved to the vendor side: a hub agent may correct the *quantity* he
     * counted, never the price the vendor sells at.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission(StockPermissions::CREATE_PRODUCT)
            && $this->belongsToActor($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    /**
     * Correcting the recorded quantity.
     *
     * Open to both sides: the vendor counting his own shelf, and a hub agent
     * correcting what our depot actually holds.
     */
    public function adjust(User $user, Product $product): bool
    {
        if ($user->hasPermission(StockPermissions::ADMIN_OVERRIDE)) {
            return true;
        }

        if ($user->hasPermission(StockPermissions::ADJUST)) {
            return $this->belongsToActor($user, $product);
        }

        return false;
    }

    /**
     * Quarantining a defective reference. Hub side only, by design: it is a
     * statement about the goods sitting on our shelf.
     */
    public function block(User $user, Product $product): bool
    {
        return $user->hasPermission(StockPermissions::ADMIN_OVERRIDE);
    }

    /**
     * Reading the full movement ledger of a product.
     */
    public function viewLedger(User $user, Product $product): bool
    {
        if ($user->hasPermission(StockPermissions::ADMIN_OVERRIDE)) {
            return true;
        }

        return $user->hasPermission(StockPermissions::VIEW) && $this->belongsToActor($user, $product);
    }

    /**
     * Whether the product belongs to the vendor account the actor works for.
     *
     * A team member acts on his employer's catalog, which is why the comparison
     * is against accountOwnerId() and not against the user id.
     */
    private function belongsToActor(User $user, Product $product): bool
    {
        return (int) $product->seller_id === $user->accountOwnerId();
    }
}
