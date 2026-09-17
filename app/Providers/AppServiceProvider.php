<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use App\Services\Chatbot\ChatDriverException;
use App\Services\Chatbot\Contracts\ChatDriver;
use App\Services\Chatbot\Drivers\GeminiDriver;
use App\Services\Chatbot\Drivers\OpenAiDriver;
use App\Support\EcommerceSyncHeartbeat;
use App\Support\StoreContext;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        $this->registerChatDriver();
    }

    /**
     * Resolve the assistant's AI provider from configuration.
     *
     * Bound by contract rather than by class so the chatbot never names a
     * vendor: swapping AI_DRIVER is the only change needed to move the whole
     * assistant from Gemini to OpenAI or back.
     */
    private function registerChatDriver(): void
    {
        $this->app->bind(ChatDriver::class, function (): ChatDriver {
            return match ((string) config('ai.driver')) {
                'gemini' => $this->app->make(GeminiDriver::class),
                'openai' => $this->app->make(OpenAiDriver::class),
                default => throw ChatDriverException::unknownDriver((string) config('ai.driver')),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // One policy for every password the platform accepts, whether it is
        // typed on the seller registration form or by an admin creating a
        // staff account.
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());

        Order::observe(OrderObserver::class);

        $this->ensurePublicStorageLink();
        $this->registerPdfTextDirectives();
        $this->registerEcommerceSyncHeartbeat();
    }

    /**
     * Auto-sync is scheduled every five minutes, but `artisan serve` never
     * runs the scheduler. After the response is sent, pick up any connector
     * whose next_sync_at has passed so the toggle works in local dev too.
     */
    private function registerEcommerceSyncHeartbeat(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $this->app->terminating(static fn () => EcommerceSyncHeartbeat::tick());
    }

    /**
     * Recreate `public/storage` when a deploy skipped `storage:link`.
     *
     * Uploaded files live under `storage/app/public`; the web server only
     * serves them through that symlink. Hosts that wipe `public/` on each
     * release (or never run the artisan command) then 404 every photo and
     * document. The `/storage/{path}` HTTP route still answers if the link
     * cannot be created.
     */
    private function ensurePublicStorageLink(): void
    {
        if ($this->app->environment('testing')) {
            return;
        }

        $link = public_path('storage');
        $target = storage_path('app/public');

        if (is_link($link) || file_exists($link)) {
            return;
        }

        if (! is_dir($target) && ! mkdir($target, 0755, true) && ! is_dir($target)) {
            return;
        }

        try {
            symlink($target, $link);
        } catch (\Throwable) {
            // The /storage/{path} route still serves the files.
        }
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
