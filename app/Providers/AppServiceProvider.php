<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use App\Support\StoreContext;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped rather than singleton: the queue worker flushes scoped
        // instances between jobs, so a job can never inherit the store of
        // whatever ran before it.
        $this->app->scoped(StoreContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Order::observe(OrderObserver::class);

        $this->registerPdfTextDirectives();
    }

    /**
     * Blade helpers for the PDF templates (labels, delivery notes, invoices).
     *
     * Dompdf has no bidirectional support, so any field a seller may fill in
     * Arabic goes through the "bidi" directive, and the block that holds it
     * through "bidiclass" to be aligned on the right edge. Fields long enough
     * to wrap use "bidilines", which breaks the lines itself so that they stay
     * in reading order.
     */
    private function registerPdfTextDirectives(): void
    {
        Blade::directive('bidi', fn (string $expression) => "<?php echo e(\App\Support\ArabicText::render($expression)); ?>");

        Blade::directive('bidilines', fn (string $expression) => "<?php echo implode('<br>', array_map('e', \App\Support\ArabicText::lines($expression))); ?>");

        Blade::directive('bidiclass', fn (string $expression) => "<?php echo \App\Support\ArabicText::cssClass($expression); ?>");
    }
}
