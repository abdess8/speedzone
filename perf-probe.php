<?php

/**
 * Temporary performance probe. Boots the app, logs in a user and dispatches real
 * requests through the HTTP kernel while recording query counts, timings and
 * payload sizes. Delete after the audit.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/** @var Illuminate\Contracts\Http\Kernel $kernel */
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

function section(string $title): void
{
    echo PHP_EOL.str_repeat('=', 78).PHP_EOL.$title.PHP_EOL.str_repeat('=', 78).PHP_EOL;
}

function logoutQuietly(): void
{
    try {
        Auth::guard('web')->logout();
    } catch (Throwable $e) {
        // guard swapped by the request lifecycle; ignore
    }
}

section('ENVIRONMENT');
echo 'APP_ENV        : '.config('app.env').PHP_EOL;
echo 'APP_DEBUG      : '.var_export(config('app.debug'), true).PHP_EOL;
echo 'CACHE store    : '.config('cache.default').PHP_EOL;
echo 'SESSION driver : '.config('session.driver').PHP_EOL;
echo 'QUEUE          : '.config('queue.default').PHP_EOL;
echo 'LOG level      : '.config('logging.channels.stack.channels')[0].' / '.env('LOG_LEVEL').PHP_EOL;
echo 'config cached  : '.var_export(file_exists($app->getCachedConfigPath()), true).PHP_EOL;
echo 'routes cached  : '.var_export(file_exists($app->getCachedRoutesPath()), true).PHP_EOL;
echo 'events cached  : '.var_export(file_exists($app->getCachedEventsPath()), true).PHP_EOL;

section('DATA VOLUME');
foreach (['orders', 'users', 'notifications', 'order_status_histories', 'cities', 'permissions', 'roles', 'sessions'] as $table) {
    try {
        printf("%-24s %s rows\n", $table, number_format(DB::table($table)->count()));
    } catch (Throwable $e) {
        printf("%-24s ERROR: %s\n", $table, $e->getMessage());
    }
}

section('TRANSLATION PAYLOAD (shared on EVERY request)');
$groups = [
    'sidebar', 'navbar', 'roles', 'common', 'orders', 'pickups', 'transfers', 'returns',
    'invoices', 'driver_invoices', 'driver_finance', 'driver_invoice_statuses',
    'driver_transaction_types', 'driver_transaction_statuses', 'billing_frequencies',
    'seller_payment_methods', 'users', 'cities', 'partners', 'partner_auth_types',
    'sectors', 'driver_zones', 'profile', 'support_tickets', 'support_ticket_statuses',
    'support_ticket_categories', 'support_object_types', 'permissions', 'notifications',
    'seller_registration', 'user_statuses', 'dashboard', 'order_statuses', 'payment_methods',
];
$total = 0;
$rows = [];
foreach ($groups as $g) {
    $size = strlen(json_encode(trans($g), JSON_UNESCAPED_UNICODE));
    $rows[$g] = $size;
    $total += $size;
}
arsort($rows);
echo 'groups shared      : '.count($groups).PHP_EOL;
echo 'total JSON bytes   : '.number_format($total).' ('.round($total / 1024, 1).' KB)'.PHP_EOL;
echo 'top 10 heaviest    :'.PHP_EOL;
foreach (array_slice($rows, 0, 10, true) as $g => $size) {
    printf("   %-28s %8s bytes\n", $g, number_format($size));
}

// ---------------------------------------------------------------------------
// Pick the heaviest realistic user: an admin (sees all orders).
// ---------------------------------------------------------------------------
$user = User::query()
    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Admin', 'SuperAdmin']))
    ->first() ?? User::query()->first();

if (! $user) {
    echo PHP_EOL.'!! No users in database, cannot probe requests.'.PHP_EOL;
    exit(1);
}

section('PROBE USER');
echo "id={$user->id} name={$user->name} email={$user->email}".PHP_EOL;
echo 'roles: '.$user->roles->pluck('name')->implode(', ').PHP_EOL;
echo 'permission count (roles+direct): '.$user->roles->flatMap->permissions->merge($user->permissions)->pluck('name')->unique()->count().PHP_EOL;

section('SHARED PROP SIZES (auth.user / permissions)');
Auth::guard('web')->login($user);
$fresh = User::find($user->id);
$fresh->loadMissing('roles.permissions', 'permissions');
$authArray = $fresh->toArray();
echo 'auth.user JSON bytes      : '.number_format(strlen(json_encode($authArray))).PHP_EOL;
echo 'auth.user top-level keys  : '.count($authArray).PHP_EOL;
echo 'relations leaked into it  : '.implode(', ', array_keys(array_filter($authArray, 'is_array'))).PHP_EOL;
if (isset($authArray['roles'])) {
    echo 'roles serialized          : '.number_format(strlen(json_encode($authArray['roles']))).' bytes'.PHP_EOL;
}
if (isset($authArray['permissions'])) {
    echo 'permissions serialized    : '.number_format(strlen(json_encode($authArray['permissions']))).' bytes'.PHP_EOL;
}
logoutQuietly();

/**
 * Dispatch a real request through the HTTP kernel with the user authenticated.
 */
function probe(string $label, string $uri, User $user, Illuminate\Contracts\Http\Kernel $kernel, array $headers = []): void
{
    $queries = [];
    DB::flushQueryLog();
    DB::listen(function ($q) use (&$queries) {
        $queries[] = ['sql' => $q->sql, 'time' => $q->time];
    });

    // Inertia keeps shared props on a singleton. Real requests each get a fresh
    // process; this script does not, so props shared by the previous request
    // would otherwise leak into this one.
    Inertia\Inertia::flushShared();

    Auth::guard('web')->login($user);

    $request = Request::create($uri, 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->headers->set('Accept', 'text/html');

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    $start = microtime(true);
    $response = $kernel->handle($request);
    $elapsed = (microtime(true) - $start) * 1000;

    $content = $response->getContent();
    $dbTime = array_sum(array_column($queries, 'time'));

    section("REQUEST: {$label}  ({$uri})");
    echo 'HTTP status         : '.$response->getStatusCode().PHP_EOL;
    echo 'wall time           : '.round($elapsed, 1).' ms'.PHP_EOL;
    echo 'SQL queries         : '.count($queries).PHP_EOL;
    echo 'SQL total time      : '.round($dbTime, 1).' ms'.PHP_EOL;
    echo 'PHP time (non-SQL)  : '.round($elapsed - $dbTime, 1).' ms'.PHP_EOL;
    echo 'response bytes      : '.number_format(strlen($content)).' ('.round(strlen($content) / 1024, 1).' KB)'.PHP_EOL;
    echo 'peak memory         : '.round(memory_get_peak_usage(true) / 1048576, 1).' MB'.PHP_EOL;

    // Break the Inertia page object down prop by prop. A full document carries
    // it in data-page, an Inertia XHR navigation returns it as the whole body.
    $page = null;

    if (preg_match('/data-page="([^"]*)"/', $content, $m)) {
        $page = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
    } elseif (str_starts_with(ltrim($content), '{')) {
        $decoded = json_decode($content, true);
        $page = isset($decoded['component']) ? $decoded : null;
    }

    if (is_array($page)) {
        $propSizes = [];
        foreach (($page['props'] ?? []) as $key => $value) {
            $propSizes[$key] = strlen(json_encode($value));
        }
        arsort($propSizes);
        echo PHP_EOL.'--- Inertia props by size (JSON bytes) ---'.PHP_EOL;
        printf("   %-24s %10s\n", 'component', $page['component'] ?? '?');
        foreach ($propSizes as $key => $size) {
            printf("   %-24s %10s\n", $key, number_format($size));
        }
        printf("   %-24s %10s\n", 'TOTAL props', number_format(array_sum($propSizes)));

        $authUser = $page['props']['auth']['user'] ?? null;
        if (is_array($authUser)) {
            $fieldSizes = [];
            foreach ($authUser as $k => $v) {
                $fieldSizes[$k] = strlen(json_encode($v));
            }
            arsort($fieldSizes);
            echo PHP_EOL.'--- auth.user fields by size ---'.PHP_EOL;
            foreach (array_slice($fieldSizes, 0, 8, true) as $k => $size) {
                printf("   %-24s %10s\n", $k, number_format($size));
            }
        }
    }

    // Group identical queries to expose duplicates.
    $normalized = [];
    foreach ($queries as $q) {
        $key = preg_replace('/\s+/', ' ', $q['sql']);
        $normalized[$key]['count'] = ($normalized[$key]['count'] ?? 0) + 1;
        $normalized[$key]['time'] = ($normalized[$key]['time'] ?? 0) + $q['time'];
    }
    uasort($normalized, fn ($a, $b) => $b['time'] <=> $a['time']);

    echo PHP_EOL.'--- distinct query shapes: '.count($normalized).' ---'.PHP_EOL;
    $i = 0;
    foreach ($normalized as $sql => $info) {
        if ($i++ >= 15) {
            echo '   ... ('.(count($normalized) - 15).' more shapes)'.PHP_EOL;
            break;
        }
        printf("   x%-3d %7sms  %s\n", $info['count'], round($info['time'], 1), substr($sql, 0, 150));
    }

    $dupes = array_filter($normalized, fn ($i) => $i['count'] > 1);
    if ($dupes) {
        echo PHP_EOL.'--- DUPLICATED queries (executed more than once) ---'.PHP_EOL;
        foreach ($dupes as $sql => $info) {
            printf("   x%-3d %7sms  %s\n", $info['count'], round($info['time'], 1), substr($sql, 0, 140));
        }
    }

    logoutQuietly();
}

probe('Dashboard document (Inertia shell)', '/dashboard', $user, $kernel);
probe('Orders index', '/orders', $user, $kernel);
probe('Notifications JSON', '/notifications', $user, $kernel);

// The locale the browser would already be holding for this user, and the asset
// version Inertia expects (a wrong one triggers a version-conflict response).
$warmLocale = $user->locale ?? config('app.locale');
$assetVersion = (string) (new App\Http\Middleware\HandleInertiaRequests)
    ->version(Request::create('/orders', 'GET'));

// An in-app navigation: the browser already holds the translations, so it
// advertises its locale and the server must not resend the bundle.
probe('Orders index (Inertia navigation, warm locale)', '/orders', $user, $kernel, [
    'X-Inertia' => 'true',
    'X-Inertia-Version' => $assetVersion,
    'X-Inertia-Locale' => $warmLocale,
    'Accept' => 'text/html, application/xhtml+xml',
]);

// Paging/sorting/filtering: only the table is requested.
probe('Orders index (partial reload: table only)', '/orders?page=2', $user, $kernel, [
    'X-Inertia' => 'true',
    'X-Inertia-Version' => $assetVersion,
    'X-Inertia-Locale' => $warmLocale,
    'X-Inertia-Partial-Data' => 'orders,filters',
    'X-Inertia-Partial-Component' => 'orders/index',
    'Accept' => 'text/html, application/xhtml+xml',
]);

// ---------------------------------------------------------------------------
// Dashboard service itself (the /api/dashboard payload) — measured directly so
// the cache does not hide the cost.
// ---------------------------------------------------------------------------
section('DashboardService::build (cold, cache bypassed)');
Auth::guard('web')->login($user);
app()->setLocale('fr');
$range = App\Support\DashboardDateRange::fromRequest(Request::create('/api/dashboard', 'GET', ['period' => 'last_30_days']));

$queries = [];
DB::listen(function ($q) use (&$queries) {
    $queries[] = ['sql' => $q->sql, 'time' => $q->time];
});

$svc = app(App\Services\DashboardService::class);
$ref = new ReflectionClass($svc);
$build = $ref->getMethod('build');
$build->setAccessible(true);

$start = microtime(true);
$data = $build->invoke($svc, $user, $range);
$elapsed = (microtime(true) - $start) * 1000;

$dbTime = array_sum(array_column($queries, 'time'));
echo 'wall time         : '.round($elapsed, 1).' ms'.PHP_EOL;
echo 'SQL queries       : '.count($queries).PHP_EOL;
echo 'SQL total time    : '.round($dbTime, 1).' ms'.PHP_EOL;
echo 'PHP time          : '.round($elapsed - $dbTime, 1).' ms'.PHP_EOL;
echo 'payload bytes     : '.number_format(strlen(json_encode($data))).PHP_EOL;

$normalized = [];
foreach ($queries as $q) {
    $key = preg_replace('/\s+/', ' ', $q['sql']);
    $normalized[$key]['count'] = ($normalized[$key]['count'] ?? 0) + 1;
    $normalized[$key]['time'] = ($normalized[$key]['time'] ?? 0) + $q['time'];
}
uasort($normalized, fn ($a, $b) => $b['time'] <=> $a['time']);

echo PHP_EOL.'--- all query shapes (sorted by total time) ---'.PHP_EOL;
foreach ($normalized as $sql => $info) {
    printf("   x%-3d %7sms  %s\n", $info['count'], round($info['time'], 1), substr($sql, 0, 190));
}

$dupes = array_filter($normalized, fn ($i) => $i['count'] > 1);
if ($dupes) {
    echo PHP_EOL.'--- DUPLICATED dashboard queries ---'.PHP_EOL;
    foreach ($dupes as $sql => $info) {
        printf("   x%-3d %7sms  %s\n", $info['count'], round($info['time'], 1), substr($sql, 0, 160));
    }
}

echo PHP_EOL.'DONE'.PHP_EOL;
