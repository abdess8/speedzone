<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;
use App\Support\SupportPermissions;

class SupportTicketPolicy
{
    /**
     * Evaluate a policy ability without the global Gate::before super-admin
     * bypass. Use for stateful actions (reply, close, update_status) so a
     * closed ticket stays read-only even for super admins.
     */
    public function allows(string $ability, User $user, ?SupportTicket $ticket = null): bool
    {
        return match ($ability) {
            'viewAny' => $this->viewAny($user),
            'view' => $ticket ? $this->view($user, $ticket) : false,
            'create' => $this->create($user),
            'reply' => $ticket ? $this->reply($user, $ticket) : false,
            'assign' => $ticket ? $this->assign($user, $ticket) : $this->isStaff($user),
            'updateStatus' => $ticket ? $this->updateStatus($user, $ticket) : false,
            'close' => $ticket ? $this->close($user, $ticket) : false,
            default => false,
        };
    }

    public function viewAny(User $user): bool
    {
        foreach (SupportPermissions::moduleAccess() as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        if ($this->isStaff($user)) {
            return true;
        }

        if ($ticket->assigned_to === $user->id) {
            return true;
        }

        return $ticket->created_by === $user->id && $user->hasPermission(SupportPermissions::READ_OWN);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(SupportPermissions::CREATE);
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        if ($ticket->isClosed()) {
            return false;
        }

        if (! $this->view($user, $ticket)) {
            return false;
        }

        if ($this->isStaff($user) || $ticket->assigned_to === $user->id) {
            return $user->hasPermission(SupportPermissions::REPLY)
                || $user->hasPermission(SupportPermissions::MANAGE)
                || $user->hasPermission(SupportPermissions::READ_ALL);
        }

        return $ticket->created_by === $user->id && $user->hasPermission(SupportPermissions::REPLY);
    }

    public function assign(User $user, SupportTicket $ticket): bool
    {
        return $user->hasPermission(SupportPermissions::ASSIGN)
            || $user->hasPermission(SupportPermissions::MANAGE);
    }

    public function updateStatus(User $user, SupportTicket $ticket): bool
    {
        if ($ticket->isClosed()) {
            return false;
        }

        return $user->hasPermission(SupportPermissions::UPDATE_STATUS)
            || $user->hasPermission(SupportPermissions::MANAGE);
    }

    public function close(User $user, SupportTicket $ticket): bool
    {
        if ($ticket->isClosed()) {
            return false;
        }

        if ($this->isStaff($user)) {
            return $user->hasPermission(SupportPermissions::CLOSE)
                || $user->hasPermission(SupportPermissions::UPDATE_STATUS)
                || $user->hasPermission(SupportPermissions::MANAGE);
        }

        return $ticket->created_by === $user->id && $user->hasPermission(SupportPermissions::CLOSE);
    }

    /**
     * Whether the user is back-office support staff (sees every ticket).
     */
    private function isStaff(User $user): bool
    {
        foreach (SupportPermissions::staffAccess() as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
