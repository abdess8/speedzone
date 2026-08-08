<?php

namespace App\Support;

/**
 * Canonical permission names for the vendor fulfilment module.
 *
 * Split in two families on purpose:
 *  - the vendor family, which an account owner may delegate to his team through
 *    a custom role (see RolePermissionMatrix::sellerCeiling());
 *  - the hub family, which represents *our* operations on someone else's goods
 *    and is never delegatable to the vendor side.
 */
final class StockPermissions
{
    /* ------------------------------------------------------------- vendor */

    public const VIEW = 'stock.view';

    public const CREATE_PRODUCT = 'stock.create_product';

    /**
     * Opening a whole catalog from a spreadsheet.
     *
     * Split from CREATE_PRODUCT because the two carry very different blast
     * radii: a warehouse employee entrusted with adding the odd reference is not
     * necessarily entrusted with rewriting the price list a thousand rows at a
     * time.
     */
    public const IMPORT_PRODUCTS = 'stock.import_products';

    public const CREATE_INBOUND = 'stock.create_inbound';

    public const ADJUST = 'stock.adjust';

    public const ORDERS_CREATE_WITH_STOCK = 'orders.create_with_stock';

    /* ---------------------------------------------------------------- hub */

    /**
     * Going to the shop, counting what is handed over and driving it to the depot.
     *
     * Hub-side for the same reason the depot count is: the value of a collection
     * figure is that the person signing it has nothing to gain from it.
     */
    public const COLLECT_INBOUND = 'stock.collect_inbound';

    /** Counting the parcel in at the depot and correcting the declared figures. */
    public const RECEIVE_INBOUND = 'stock.receive_inbound';

    /** Cross-vendor audit of every movement, plus quarantining a product. */
    public const ADMIN_OVERRIDE = 'stock.admin_override';

    /**
     * Vendor grants an owner may hand to a team member.
     *
     * @return array<int, string>
     */
    public static function sellerDefaults(): array
    {
        return [
            self::VIEW,
            self::CREATE_PRODUCT,
            self::IMPORT_PRODUCTS,
            self::CREATE_INBOUND,
            self::ADJUST,
            self::ORDERS_CREATE_WITH_STOCK,
        ];
    }

    /**
     * Grants that open the depot side of the module.
     *
     * @return array<int, string>
     */
    public static function staffDefaults(): array
    {
        return [
            self::VIEW,
            self::COLLECT_INBOUND,
            self::RECEIVE_INBOUND,
        ];
    }

    /**
     * Permissions that reveal the module in navigation.
     *
     * Collection is in the list because a driver holds nothing else in this
     * module, and a round he cannot see is a round he cannot drive.
     *
     * @return array<int, string>
     */
    public static function moduleAccess(): array
    {
        return [self::VIEW, self::COLLECT_INBOUND, self::RECEIVE_INBOUND, self::ADMIN_OVERRIDE];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            ...self::sellerDefaults(),
            self::COLLECT_INBOUND,
            self::RECEIVE_INBOUND,
            self::ADMIN_OVERRIDE,
        ];
    }
}
