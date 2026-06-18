<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('transfers.read')
            || $user->hasPermission('transfers.read.assigned');
    }

    public function view(User $user, Transfer $transfer): bool
    {
        return $user->hasTransferScopePermission('read', $transfer);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('transfers.create');
    }

    public function update(User $user, Transfer $transfer): bool
    {
        if (! $user->hasPermission('transfers.update')) {
            return false;
        }

        $status = $transfer->status instanceof \App\Enums\TransferStatus
            ? $transfer->status
            : \App\Enums\TransferStatus::from($transfer->status);

        return $status->isEditable();
    }

    public function assignStaff(User $user, Transfer $transfer): bool
    {
        if (! $user->hasPermission('transfers.update')) {
            return false;
        }

        $status = $transfer->status instanceof \App\Enums\TransferStatus
            ? $transfer->status
            : \App\Enums\TransferStatus::from($transfer->status);

        return $status->canAssignStaff();
    }

    public function changeStatus(User $user, Transfer $transfer): bool
    {
        return $this->dispatch($user, $transfer)
            || $this->receive($user, $transfer)
            || ($user->hasPermission('transfers.update') && $this->update($user, $transfer));
    }

    public function dispatch(User $user, Transfer $transfer): bool
    {
        return $user->hasPermission('transfers.dispatch');
    }

    public function receive(User $user, Transfer $transfer): bool
    {
        if (! $user->hasPermission('transfers.receive')) {
            return false;
        }

        return $user->hasTransferScopePermission('read', $transfer);
    }

    public function scan(User $user, Transfer $transfer): bool
    {
        if (! $user->hasPermission('transfers.receive') && ! $user->hasPermission('transfers.dispatch')) {
            return false;
        }

        return $user->hasTransferScopePermission('read', $transfer);
    }

    public function printQr(User $user, Transfer $transfer): bool
    {
        return $user->hasTransferScopePermission('read', $transfer);
    }
}
