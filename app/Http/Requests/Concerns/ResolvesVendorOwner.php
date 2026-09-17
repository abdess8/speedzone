<?php

namespace App\Http\Requests\Concerns;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\AdminSellerDirectory;
use Closure;

/**
 * Uniqueness and `exists` rules for vendor-owned rows.
 *
 * Super admins edit another account's shops and team, so the owner id must
 * come from that account — never from the admin's own user row, which would
 * make every uniqueness check miss and every `exists` rule reject the payload.
 */
trait ResolvesVendorOwner
{
    protected function vendorOwnerId(): int
    {
        $user = $this->user();

        if (! $user->isSuperAdmin()) {
            return $user->accountOwnerId();
        }

        return $this->vendorOwnerIdFromRoute() ?? $this->integer('seller_id');
    }

    protected function vendorOwnerIdFromRoute(): ?int
    {
        $store = $this->route('store');
        if ($store instanceof Store) {
            return (int) $store->owner_id;
        }

        $member = $this->route('member');
        if ($member instanceof User && $member->parent_user_id) {
            return (int) $member->parent_user_id;
        }

        $role = $this->route('role');
        if ($role instanceof Role && $role->owner_id) {
            return (int) $role->owner_id;
        }

        return null;
    }

    /**
     * Required on admin creates only. Updates take the owner from the row.
     *
     * @return array<string, mixed>
     */
    protected function sellerIdRules(): array
    {
        if (! $this->user()?->isSuperAdmin() || $this->vendorOwnerIdFromRoute() !== null) {
            return [];
        }

        return [
            'seller_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! AdminSellerDirectory::find((int) $value)) {
                        $fail(__('stores.admin.errors.seller_required'));
                    }
                },
            ],
        ];
    }
}
