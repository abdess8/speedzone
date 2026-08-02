<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoices.read.all') || $user->hasPermission('invoices.read.own');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->hasPermission('invoices.read.all')) {
            return true;
        }

        return $invoice->seller_id === $user->id && $user->hasPermission('invoices.read.own');
    }

    /**
     * Generating invoices (manual admin generation) requires the generate
     * permission. There is no per-model owner here.
     */
    public function generate(User $user): bool
    {
        return $user->hasPermission('invoices.generate');
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.pay') && $this->statusIs($invoice, [InvoiceStatus::GENERATED, InvoiceStatus::SENT]);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.cancel') && $this->statusIs($invoice, [InvoiceStatus::GENERATED, InvoiceStatus::SENT]);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.delete') && $this->statusIs($invoice, [InvoiceStatus::CANCELLED]);
    }

    public function print(User $user, Invoice $invoice): bool
    {
        if (! $user->hasPermission('invoices.print') && ! $this->view($user, $invoice)) {
            return false;
        }

        return $this->view($user, $invoice);
    }

    /**
     * @param  array<int, InvoiceStatus>  $statuses
     */
    private function statusIs(Invoice $invoice, array $statuses): bool
    {
        $status = $invoice->status instanceof InvoiceStatus
            ? $invoice->status
            : InvoiceStatus::from($invoice->status);

        return in_array($status, $statuses, true);
    }
}
