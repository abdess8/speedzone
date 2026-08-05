<?php

namespace App\Policies;

use App\Models\OrderReturn;
use App\Models\User;

class OrderReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessReturnsModule();
    }

    public function view(User $user, OrderReturn $return): bool
    {
        return $user->hasReturnScopePermission('read', $return);
    }

    public function create(User $user): bool
    {
        return $user->canCreateReturnRequest()
            || $user->canCreateDriverReturn()
            || $user->hasPermission('returns.manage');
    }

    public function createForOrder(User $user): bool
    {
        return $this->create($user);
    }

    public function updateStatus(User $user, OrderReturn $return): bool
    {
        if ($return->isTerminal()) {
            return false;
        }

        return $user->hasPermission('returns.manage')
            || ($user->canUpdateReturnStatus() && $user->hasReturnScopePermission('update_status', $return));
    }

    public function changeStatus(User $user, OrderReturn $return): bool
    {
        return $this->updateStatus($user, $return);
    }

    /**
     * Naming the driver for the last mile is a dispatch act, so it follows the
     * grant that opens that step rather than the blanket status permission.
     */
    public function assignDriver(User $user, OrderReturn $return): bool
    {
        if ($return->isTerminal()) {
            return false;
        }

        return $user->hasPermission('returns.manage')
            || $user->hasPermission('returns.update_status')
            || $user->hasPermission('returns.transition.to_in_delivery_to_vendor');
    }

    public function editCustomerData(User $user, OrderReturn $return): bool
    {
        if (! $return->canEditCustomerData()) {
            return false;
        }

        if ($user->hasPermission('returns.edit_customer_data') || $user->hasPermission('returns.manage')) {
            return true;
        }

        return $return->order?->seller_id === $user->id
            && $user->canCreateReturnRequest();
    }

    public function scan(User $user, OrderReturn $return): bool
    {
        if ($return->isTerminal()) {
            return false;
        }

        return $user->canUpdateReturnStatus()
            || $user->hasPermission('returns.create');
    }

    public function printQr(User $user, OrderReturn $return): bool
    {
        return $user->hasReturnScopePermission('read', $return);
    }
}
