<?php

namespace App\Events;

use App\Models\StockReception;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A vendor has a parcel ready and is waiting for somebody to come and get it.
 */
class StockPickupRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly StockReception $reception) {}
}
