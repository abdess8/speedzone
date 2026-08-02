<?php

namespace App\Policies;

use App\Enums\DriverInvoiceStatus;
use App\Models\DriverInvoice;
use App\Models\User;

class DriverInvoicePolicy
{
    /**
     * Evaluate a policy ability without the global Gate::before super-admin bypass.
     * Use this for stateful actions (pay, cancel, delete) so invoice status is
     * always enforced even for super admins.
     */
    public function allows(string $ability, User $user, ?DriverInvoice $invoice = null): bool
    {
        return match ($ability) {
            'viewAny' => $this->viewAny($user),
            'view' => $invoice ? $this->view($user, $invoice) : false,
            'generate' => $this->generate($user),
            'pay' => $invoice ? $this->pay($user, $invoice) : false,
            'cancel' => $invoice ? $this->cancel($user, $invoice) : false,
            'delete' => $invoice ? $this->delete($user, $invoice) : false,
            'print' => $invoice ? $this->print($user, $invoice) : false,
            default => false,
        };
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('driver_invoices.read.all') || $user->hasPermission('driver_invoices.read.own');
    }

    public function view(User $user, DriverInvoice $invoice): bool
    {
        if ($user->hasPermission('driver_invoices.read.all')) {
            return true;
        }

        return $invoice->driver_id === $user->id && $user->hasPermission('driver_invoices.read.own');
    }

    /**
     * Generating invoices (manual admin generation) requires the generate
     * permission. There is no per-model owner here.
     */
    public function generate(User $user): bool
    {
        return $user->hasPermission('driver_invoices.generate');
    }

    public function pay(User $user, DriverInvoice $invoice): bool
    {
        return $user->hasPermission('driver_invoices.pay') && $this->statusIs($invoice, [DriverInvoiceStatus::GENERATED]);
    }

    public function cancel(User $user, DriverInvoice $invoice): bool
    {
        return $user->hasPermission('driver_invoices.cancel') && $this->statusIs($invoice, [DriverInvoiceStatus::GENERATED]);
    }

    public function delete(User $user, DriverInvoice $invoice): bool
    {
        return $user->hasPermission('driver_invoices.delete') && $this->statusIs($invoice, [DriverInvoiceStatus::CANCELLED]);
    }

    public function print(User $user, DriverInvoice $invoice): bool
    {
        return $this->view($user, $invoice) || $user->hasPermission('driver_invoices.print');
    }

    /**
     * @param  array<int, DriverInvoiceStatus>  $statuses
     */
    private function statusIs(DriverInvoice $invoice, array $statuses): bool
    {
        $status = $invoice->status instanceof DriverInvoiceStatus
            ? $invoice->status
            : DriverInvoiceStatus::from($invoice->status);

        return in_array($status, $statuses, true);
    }
}
