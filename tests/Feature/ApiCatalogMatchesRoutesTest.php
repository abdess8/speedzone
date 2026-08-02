<?php

use Illuminate\Support\Facades\Route;

/**
 * Keeps the published API surface honest.
 *
 * The documentation page and the downloadable Postman collection are both
 * rendered from `apiCatalog.js`. Nothing stops someone renaming a route in
 * `routes/api.php` and leaving the catalogue behind, at which point we would be
 * handing integrators requests that 404. This walks the catalogue and asserts
 * every entry still resolves to a registered route.
 */
function documentedEndpoints(): array
{
    $catalogue = file_get_contents(
        resource_path('js/Pages/settings/Partials/apiCatalog.js')
    );

    preg_match_all(
        "/method:\s*'([A-Z]+)',\s*\n\s*path:\s*'([^']+)'/",
        $catalogue,
        $matches,
        PREG_SET_ORDER
    );

    return array_map(
        fn (array $match) => ['method' => $match[1], 'path' => $match[2]],
        $matches
    );
}

/**
 * Registered API routes as `METHOD /api/some/{param}` with parameter names
 * flattened, so the catalogue is free to call `{order}` whatever reads best.
 */
function registeredApiRoutes(): array
{
    $registered = [];

    foreach (Route::getRoutes() as $route) {
        $uri = '/'.preg_replace('/\{[^}]+\}/', '{}', $route->uri());

        foreach ($route->methods() as $method) {
            $registered[$method.' '.$uri] = true;
        }
    }

    return $registered;
}

it('documents only endpoints that are actually registered', function () {
    $endpoints = documentedEndpoints();
    $registered = registeredApiRoutes();

    expect($endpoints)->not->toBeEmpty(
        'The endpoint catalogue could not be parsed — did its shape change?'
    );

    $unknown = [];

    foreach ($endpoints as $endpoint) {
        $signature = $endpoint['method'].' '.preg_replace('/\{[^}]+\}/', '{}', $endpoint['path']);

        if (! isset($registered[$signature])) {
            $unknown[] = $signature;
        }
    }

    expect($unknown)->toBe([], 'Documented endpoints with no matching route: '.implode(', ', $unknown));
});

it('documents every order endpoint a vendor can reach', function () {
    $documented = array_map(
        fn (array $endpoint) => $endpoint['method'].' '.$endpoint['path'],
        documentedEndpoints()
    );

    // The vendor-facing contract: anything added here later should be
    // documented rather than silently shipped.
    expect($documented)->toContain(
        'GET /api/orders',
        'POST /api/orders',
        'GET /api/orders/{order}',
        'PUT /api/orders/{order}',
        'DELETE /api/orders/{order}',
        'GET /api/orders/{order}/tracking',
        'GET /api/orders/track/{tracking_number}',
        'POST /api/pickup-requests',
        'GET /api/cities',
        'GET /api/cities/{city}/sectors',
    );
});
