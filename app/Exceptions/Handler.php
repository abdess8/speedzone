<?php

namespace App\Exceptions;

use App\Support\StaleAuthCookies;
use App\Support\TranslationBundle;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * HTTP statuses rendered as an Inertia screen inside the application chrome.
     *
     * @var array<int, int>
     */
    private const INERTIA_ERROR_STATUSES = [403, 404, 419, 429, 500, 503];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $line = sprintf(
                "[%s] %s: %s in %s:%d\n",
                date('c'),
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            @file_put_contents(storage_path('logs/exceptions.log'), $line, FILE_APPEND);
        });

        $this->renderable(function (Throwable $e, Request $request) {
            return $this->renderInertiaHttpError($request, $e);
        });
    }

    /**
     * Replace Laravel's standalone error HTML with an Inertia page.
     *
     * An Inertia visit that receives a non-Inertia HTML 403 is painted as a
     * modal overlay on top of the previous screen, which hides the navbar and
     * the sidebar. Rendering a real page keeps those reachable, including on
     * a phone where navigation lives in the bottom tab bar.
     */
    private function renderInertiaHttpError(Request $request, Throwable $e): ?Response
    {
        if ($request->is('api/*') || ($request->expectsJson() && ! $request->inertia())) {
            return null;
        }

        $status = $e instanceof HttpExceptionInterface
            ? $e->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        if (! in_array($status, self::INERTIA_ERROR_STATUSES, true)) {
            return null;
        }

        // Developers still get Ignition on a 500; the overlay is useful there.
        if ($status === Response::HTTP_INTERNAL_SERVER_ERROR && config('app.debug')) {
            return null;
        }

        $user = $request->user();

        $response = Inertia::render('errors/Error', [
            'status' => $status,
            'homeUrl' => $user ? url('/dashboard') : route('login'),
            'translations' => TranslationBundle::forLocale(app()->getLocale()),
        ])
            ->toResponse($request)
            ->setStatusCode($status);

        return StaleAuthCookies::expireOn($response);
    }
}
