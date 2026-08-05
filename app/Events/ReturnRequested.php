<?php

namespace App\Events;

use App\Enums\ReturnInitiatedByRole;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly OrderReturn $return,
        public readonly Order $order,
        public readonly User $actor,
        public readonly ReturnInitiatedByRole $role,
    ) {}
}
