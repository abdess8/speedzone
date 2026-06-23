<?php

namespace App\Providers;

use App\Events\InvoiceGenerated;
use App\Events\ReturnRequested;
use App\Events\TicketClosed;
use App\Events\TicketCreated;
use App\Events\TicketMessageCreated;
use App\Listeners\SendInvoiceNotification;
use App\Listeners\SendReturnNotification;
use App\Listeners\SendTicketNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        InvoiceGenerated::class => [
            SendInvoiceNotification::class,
        ],
        TicketCreated::class => [
            SendTicketNotification::class.'@handleCreated',
        ],
        TicketMessageCreated::class => [
            SendTicketNotification::class.'@handleMessage',
        ],
        TicketClosed::class => [
            SendTicketNotification::class.'@handleClosed',
        ],
        ReturnRequested::class => [
            SendReturnNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
