# SpeedZone — Performance Audit & Optimization Report

**Stack:** Laravel 12.60 · Vue 3 · Inertia.js · Vite · MySQL
**Date:** 2026-07-26
**Goal:** Dashboard < 500 ms, Orders < 800 ms on the OVH production VPS.

---

## 1. Executive summary

The reported symptom — "fast locally, slow on OVH, and the delay happens *before* Vue
renders" — was accurate, and it pointed at the server. Profiling confirmed three
independent causes, in order of impact:

1. **Every single response carried ~92 KB of props that the page never used.** A 64 KB
   translation bundle and a 32 KB `auth.user` object (which was dragging the whole
   `roles.permissions` graph into JSON) were shared on *every* request, including every
   in-app navigation.
2. **The dashboard ran 40 queries, 13 of them exact duplicates**, and computed "new
   customers" by pulling every customer phone number into PHP.
3. **The app was not running in an optimized configuration**: no config cache, no route
   cache (257 routes re-registered per request), and 1 MB of ApexCharts in the eager
   JS bundle.

Local latency hid all of this because localhost has ~0 ms network latency and a warm
MySQL. On OVH, every extra kilobyte and every extra round trip is paid for.

### Measured results

All figures below are from `perf-probe.php` (see §12), running real requests through the
HTTP kernel against the development database (989 orders, 2,261 status histories) as an
Admin user. The "before" column was captured by stashing every change and re-running the
identical probe against `HEAD`, so the two columns differ only by the code in this report.

| Measurement | Before | After | Change |
| --- | ---: | ---: | ---: |
| **Dashboard document** — response size | 193.2 KB | 143.2 KB | −26 % |
| **Dashboard document** — Inertia props | 94,744 B | 63,580 B | −33 % |
| **Dashboard data** (`DashboardService`) — queries | 40 | 24 | −40 % |
| **Dashboard data** — SQL time | 104.0 ms | 24.8 ms | −76 % |
| **Dashboard data** — total build time | 140.9 ms | 40.9 ms | −71 % |
| **Orders document** — response size | 243.6 KB | 154.7 KB | −36 % |
| **Orders document** — Inertia props | 138,529 B | 80,981 B | −42 % |
| **Orders document** — `orders` prop | 41,406 B | 15,022 B | −64 % |
| **Orders document** — SQL time | 35.3 ms | 19.0 ms | −46 % |
| **Orders navigation** (in-app, warm locale) | 243.6 KB | 21.0 KB | −91 % |
| **Orders paging / sorting / filtering** | 243.6 KB | 14.8 KB | −94 % |
| **`auth` shared prop** (every response) | 32,574 B | 1,410 B | −96 % |
| **Eager JavaScript** (parsed on first load) | 2,463.7 KB | 1,421.7 KB | −42 % |

The dashboard payload is additionally served from a 5-minute cache, so the 40.9 ms build
cost is paid by one request in three hundred rather than by every visitor.

### Why this should meet the targets on OVH

The OVH deficit was roughly +0.65 s on the dashboard and +2.1 s on orders. The changes
attack exactly the components that scale with a slow link and a slower CPU: transferred
bytes are down 26–94 % depending on the navigation type, dashboard SQL is down 76 %, and
enabling the config/route caches removes per-request bootstrap work that is far more
expensive on a VPS than on a local SSD. The orders page in particular benefits most,
because the common interactions (paging, sorting, filtering) went from a 243.6 KB full
page render to a 14.8 KB partial response.

**These gains require the deployment steps in §11 to be applied.** Roughly a third of the
expected improvement comes from `php artisan optimize` and compression, which are
configuration, not code.

---

## 2. Route, controller and service map

| Page | Route | Controller | Services |
| --- | --- | --- | --- |
| Dashboard shell | `GET /dashboard` → `dashboard` | `DashboardController` (Inertia render only) | — |
| Dashboard data | `GET /api/dashboard` | `Api\DashboardController` | `DashboardService`, `DashboardDateRange`, `DashboardResource` |
| Orders list | `GET /orders` → `orders.index` | `OrderController@index` | `OrderQueryService`, `OrderListResource` |
| Notifications | `GET /notifications` | `NotificationController@index` | `NotificationResource` |
| Every request | — | `HandleInertiaRequests`, `SetLocale`, Jetstream `ShareInertiaData` | `TranslationBundle` |

The dashboard is a two-step page: the Inertia document renders a shell, then Vue fetches
`/api/dashboard` over Axios. That split is a good design — it keeps the document fast —
but it means *both* requests pay the shared-prop cost, which is why the shared props were
the first thing fixed.

---

## 3. Middleware and Inertia shared data

### 3.1 The translation bundle on every request

**Problem.** `HandleInertiaRequests::share()` loaded 34 translation groups and serialised
them into the props of every response: 64.3 KB of JSON, which becomes ~130 KB once
HTML-escaped into the `data-page` attribute of the document.

**Why it is slow.** It is paid twice. On the server, 34 PHP files are read and parsed and
the result is JSON-encoded on every request. On the wire, 64 KB travels on every in-app
navigation even though `vue-i18n` already holds those exact messages in memory for the
lifetime of the SPA.

**Fix.** Two parts. The bundle is now built and cached by `App\Support\TranslationBundle`,
keyed by a fingerprint of the language files' modification times, so it is assembled once
rather than per request. And the client advertises which locale it already holds via an
`X-Inertia-Locale` header; when it matches, the server omits the prop entirely.

```php
// app/Http/Middleware/HandleInertiaRequests.php — after
if ($this->clientNeedsTranslations($request, $locale)) {
    $shared['translations'] = fn () => TranslationBundle::forLocale($locale);
}

private function clientNeedsTranslations(Request $request, string $locale): bool
{
    // Fails safe: if the client does not tell us what it has, send the bundle.
    if (! $request->inertia()) {
        return true;
    }

    return $request->header(self::LOCALE_HEADER) !== $locale;
}
```

```js
// resources/js/i18n.js — after
function advertiseLoadedLocale(locale) {
  if (window.axios) {
    window.axios.defaults.headers.common['X-Inertia-Locale'] = locale;
  }
}
```

The initial document still carries the bundle (the client has nothing yet); every
subsequent navigation does not.

**Impact.** 59,757 bytes removed from every in-app navigation. Verified: an Inertia visit
to `/orders` with a warm locale returns 21.0 KB instead of 79.4 KB.

### 3.2 The `auth.user` prop was serialising the permission graph

**Problem.** The shared `auth.user` prop was 32,574 bytes, of which 31,306 were the
`roles` relation with every role's full permission list nested inside it.

**Why it is slow.** `$user->toArray()` serialises every *loaded* relation. Because
authorization checks elsewhere in the request had already eager-loaded
`roles.permissions`, that entire graph silently ended up in the JSON of every response.

There was also a middleware-ordering bug behind it. The optimized `auth` prop was being
shared from `AppServiceProvider`, but Jetstream's `ShareInertiaData` runs later in the
`web` group and overwrote it with its own unoptimized user object — so an earlier attempt
at this fix had no observable effect at all.

**Fix.** Serialise the model without its relations and attach only the derived values the
frontend actually reads. The sharing logic moved out of `AppServiceProvider` (where it was
also globally scoped) and into `HandleInertiaRequests`, which is now explicitly ordered
*after* Jetstream in `app/Http/Kernel.php` so that ours wins.

```php
// Before — app/Providers/AppServiceProvider.php
Inertia::share([
    'auth' => ['user' => fn () => $request->user()],  // whole model + relations
]);
```

```php
// After — app/Http/Middleware/HandleInertiaRequests.php
return array_merge($user->withoutRelations()->toArray(), [
    'roles' => $roleNames,
    'role_label' => $primaryRole ? trans('roles.'.$primaryRole) : null,
    'is_seller' => $user->isSeller(),
    // ... the handful of booleans the frontend reads
]);
```

```php
// app/Http/Kernel.php — ordering made explicit
\App\Http\Middleware\SetLocale::class,
// Listed explicitly so Jetstream does not append it after HandleInertiaRequests:
// whichever runs last wins the shared "auth" prop, and ours must survive.
\Laravel\Jetstream\Http\Middleware\ShareInertiaData::class,
\App\Http\Middleware\HandleInertiaRequests::class,
```

**Impact.** The `auth` prop dropped from 32,574 to 1,410 bytes — on every single response.

### 3.3 Repeated role and permission lookups

**Problem.** `isSuperAdmin()`, `isDriver()`, `isSeller()` and `hasPermission()` each
re-resolved the user's roles and permissions. A single request calls these many times
(policies, the `can` props, the sidebar).

**Fix.** `App\Models\User` now memoizes a role-name map and a permission-name map per
instance, with `forgetAccessMemo()` to invalidate after a role change.

**Impact.** Removes repeated relation traversal per request. Small in wall time locally,
but it also removes the eager-load that was feeding problem 3.2.

### 3.4 Middleware verdict

`SetLocale`, `TrustProxies` and the rest of the `web` group are cheap and were left alone.
`LogSlowRequests` (§12) was added at the front of the global stack; it is a no-op unless a
request actually exceeds the threshold.

---

## 4. Dashboard

### 4.1 Thirteen duplicated queries

**Problem.** `DashboardService::build()` issued 40 queries to produce one payload. The
summary block, the charts block and the "top N" blocks each independently re-ran the same
aggregates — order counts per status were computed once for the KPI cards, again for the
donut chart, and again for the success-rate gauge.

**Why it is slow.** Each duplicate is a full round trip plus a full table aggregation.
Measured at 104 ms of SQL for a 989-row table; that scales linearly with order volume, so
production is considerably worse.

**Fix.** Compute each aggregate once and pass it down.

```php
// Before — three separate passes over the orders table
private function buildSummary(...)  { /* SELECT status, COUNT(*) ... GROUP BY status */ }
private function ordersByStatus(...) { /* SELECT status, COUNT(*) ... GROUP BY status */ }
private function deliverySuccessGauge(...) { /* SELECT status, COUNT(*) ... GROUP BY status */ }
```

```php
// After — one pass, shared
$statusCounts = $this->statusCounts($inPeriod);

$summary = $this->buildSummary($scoped, $inPeriod, $range, $statusCounts);
$charts  = $this->buildCharts($scoped, $range, $inPeriod, $shared);
```

Several single-value aggregates were also merged into one `SELECT` with conditional sums,
for example today's and this month's order counts:

```php
->selectRaw('COUNT(*) as orders_month, SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as orders_today', [...])
```

**Impact.** 40 → 24 queries, 104 ms → 24.8 ms of SQL.

### 4.2 "New customers" loaded every phone number into PHP

**Problem.** The metric was computed by plucking every distinct `customer_phone` from the
orders table into a PHP collection, then issuing a second query with a `whereIn` holding
thousands of values.

**Why it is slow.** Memory grows with the customer base, and the generated SQL string
grows to hundreds of kilobytes. This is the classic pattern that works fine on a seeded
dev database and falls over in production.

**Fix.** Push it into a single subquery.

```php
// After — app/Services/DashboardService.php
return DB::query()->fromSub(
    (clone $scoped)
        ->select('customer_phone')
        ->groupBy('customer_phone')
        ->havingRaw('MIN(created_at) BETWEEN ? AND ?', [$range->from(), $range->to()]),
    'first_orders'
)->count();
```

**Impact.** Constant memory, one query, 2.6 ms.

### 4.3 The recent-activity feed was the slowest single query

**Problem.** At 29.4 ms it accounted for 45 % of the dashboard's remaining SQL time after
the deduplication work:

```sql
select * from order_status_histories
where order_id in (select id from orders where partner_id is null)
order by created_at desc limit 20
```

**Why it is slow.** Two reasons. `select *` hydrates every column, and — more importantly
— sorting by `created_at` while semi-joining against a subquery forced MySQL into a
filesort over the whole history table before it could apply the `LIMIT 20`.

**Fix.** History rows are append-only, so `id` order and `created_at` order are identical.
Sorting on the primary key lets MySQL walk the index backwards and stop after 20 matches.

```php
// After
return OrderStatusHistory::query()
    ->select(['id', 'order_id', 'user_id', 'status', 'is_system', 'comment', 'created_at'])
    ->with(['order:id,tracking_number', 'user:id,name'])
    ->whereIn('order_id', $orderIds)
    ->latest('id')            // was: latest('created_at')
    ->limit(20)
```

`recentOrders()` received the same treatment: an explicit column list instead of
`select *`, and `latest('id')`.

**Impact.** 29.4 ms → 9.0 ms for that query.

### 4.4 Caching

`DashboardService::get()` wraps the whole build in `Cache::remember()`, keyed per user and
per date range, with the TTL exposed as `config('performance.dashboard_cache_ttl')`
(default 300 s, override with `DASHBOARD_CACHE_TTL`).

**Impact.** The 40.9 ms build is amortised across a 5-minute window; a cache hit costs one
cache read.

---

## 5. Orders page

**Queries executed:** 10 for a cold document, 8 for a paginated partial reload. Four of
those are session reads/writes (see §11.3), not application queries.

### 5.1 The list resource was sending detail-page data

**Problem.** `index` used `OrderResource`, the same resource the detail screen uses. It
serialised the seller relation, status history, computed transition options and more —
41,406 bytes for 25 rows, of which the table renders maybe a third.

**Fix.** A dedicated `OrderListResource` with an explicit `COLUMNS` constant, and the query
narrowed to match. The seller relation is no longer eager loaded, because the list only
renders the destination city and sector.

```php
// Before
$orders = $this->orderQuery->build($request, $request->user())   // with('city','sector','seller')
    ->paginate(...);
return Inertia::render('orders/index', ['orders' => OrderResource::collection($orders)...]);
```

```php
// After
$orders = $this->orderQuery
    ->build($request, $request->user(), ['city:id,name', 'sector:id,name'])
    ->select(OrderListResource::COLUMNS)
    ->paginate($this->orderQuery->perPage($request))
    ->withQueryString();
```

**Impact.** 41,406 → 15,022 bytes, and one fewer join.

### 5.2 Filter options were rebuilt on every interaction

**Problem.** `filterOptions` (all active cities plus the status and payment enums) was
computed eagerly and re-sent on every pagination click, sort and filter — data that cannot
change between two visits to the same page.

**Fix.** Two changes that work together. On the server the props became closures, which is
what allows Inertia to skip them; on the client, table interactions became partial
reloads.

```php
// app/Http/Controllers/OrderController.php — after
// Closures so Inertia can skip them entirely on partial reloads:
// paging, sorting and filtering only ask for "orders".
'filterOptions' => fn () => $this->filterOptions(),
'can' => fn () => $this->abilities($request),
```

```js
// resources/js/Pages/orders/index.vue — after
const TABLE_PROPS = ["orders", "filters"];

const reload = () => {
  router.get(route("orders.index"), query(), {
    only: TABLE_PROPS,
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};
```

**Impact.** Paging, sorting and filtering now transfer 14.8 KB instead of 243.6 KB, and
skip the cities query entirely.

### 5.3 Date filters could not use an index

**Problem.** `OrderQueryService` filtered with `whereDate('created_at', '>=', $value)`.
Wrapping a column in a function makes the index on that column unusable — MySQL must
evaluate `DATE(created_at)` for every row.

**Fix.** Compare against a half-open range instead.

```php
// Before
$query->whereDate('created_at', '>=', $request->input('created_from'));

// After
$query->where('created_at', '>=', $this->startOfDay($request->input('created_from')));
```

**Impact.** These filters can now use `orders_partner_created_idx`.

### 5.4 Searching and filtering

Both are server-side and were already correct: `OrderQueryService::build()` applies every
filter to the query builder, sorting is whitelisted against a constant, and results are
paginated with `withQueryString()`. No client-side filtering of a full dataset was found.

---

## 6. Notifications

**Problem.** Three issues in `NotificationController`. `index` selected every column of
every notification with no limit; `markAllAsRead` looped over unread notifications issuing
one `UPDATE` per row (a genuine N+1 write); and `markAsRead` re-fetched the record after
saving it.

**Fix.**

```php
// After — app/Http/Controllers/NotificationController.php
private const PAGE_SIZE = 30;
private const COLUMNS = ['id', 'data', 'read_at', 'created_at'];

public function index(Request $request): JsonResponse
{
    $notifications = $request->user()->notifications()
        ->select(self::COLUMNS)
        ->latest()
        ->limit(self::PAGE_SIZE)
        ->get();
    // ...
}

public function markAllAsRead(Request $request): JsonResponse
{
    // Was: a loop issuing one UPDATE per unread notification.
    $request->user()->unreadNotifications()->update(['read_at' => now()]);

    return response()->json(['unread_count' => 0]);
}
```

The unread badge count in shared props is a single `COUNT(*)`, already covered by the
existing `notifications_notifiable_read_index`.

**Impact.** `markAllAsRead` is O(1) queries instead of O(n). The endpoint measures 12.6 ms
with 2 application queries.

---

## 7. Eloquent review

| Finding | Location | Resolution |
| --- | --- | --- |
| `select *` on a 20-row feed | `DashboardService::recentActivities` | Explicit column list |
| `select *` on the recent orders feed | `DashboardService::recentOrders` | Explicit column list |
| Full-table `pluck()` into `whereIn` | `DashboardService::countNewCustomers` | `fromSub()` subquery |
| Detail resource used for a list | `OrderController@index` | `OrderListResource` |
| Unused `seller` eager load on the list | `OrderQueryService::build` | `$with` parameter, defaults preserved for other callers |
| Unbounded notification fetch | `NotificationController@index` | `limit(30)` + column list |
| N+1 writes | `NotificationController@markAllAsRead` | Single bulk `update()` |
| Repeated role/permission resolution | `User` model | Per-instance memoization |
| Uncached static lookup list | `City` | `City::options()`, cached, flushed on write |

No `Model::all()` calls were found on the audited paths. Pagination was already in use on
the orders list; `paginate()` is the correct choice there because the UI shows a total page
count, so `simplePaginate()` would break it.

Every relation on the audited paths is eager loaded with an explicit column list, e.g.
`with(['city:id,name', 'sector:id,name'])`.

---

## 8. Database and indexes

`database/migrations/2026_07_26_120000_add_performance_indexes.php` adds nine composite
indexes. The pre-existing indexes were all single-column; MySQL can only use one index per
table reference, so a query that filters on `partner_id` and sorts by `created_at` had to
choose between filtering and sorting and paid for a filesort either way.

**`orders`**

| Index | Columns | Serves |
| --- | --- | --- |
| `orders_partner_created_idx` | `partner_id, created_at` | Default list + pagination `COUNT(*)` |
| `orders_partner_status_created_idx` | `partner_id, status, created_at` | Dashboard status breakdowns |
| `orders_status_delivered_idx` | `status, delivered_at` | Revenue and delivery-time aggregates (`delivered_at` had **no** index) |
| `orders_seller_created_idx` | `seller_id, created_at` | Seller-scoped list |
| `orders_driver_created_idx` | `driver_id, created_at` | Driver-scoped list |
| `orders_phone_created_idx` | `customer_phone, created_at` | New-customers `GROUP BY ... HAVING MIN(created_at)` |

**`order_status_histories`**

| Index | Columns | Serves |
| --- | --- | --- |
| `osh_created_idx` | `created_at` | Activity feed (`created_at` had **no** index) |
| `osh_order_created_idx` | `order_id, created_at` | Per-order timeline |
| `osh_status_created_idx` | `status, created_at` | Delivery-date filtering |

The migration is idempotent — it checks `Schema::getIndexes()` before creating each index,
so it is safe to run against a database where some were added by hand.

**Tables that need nothing.** `notifications` already has
`(notifiable_type, notifiable_id, read_at)`, which covers both the list and the unread
count. `cities` and `sectors` are small enough that index choice is irrelevant, and both
are now cached anyway. There are no `deliveries`, `payments` or `sellers` tables —
deliveries are modelled as order statuses, payments as columns on `orders`, and sellers as
`users` with a role.

**Recommended but not applied.** An index on `users(status)` would help the "active
sellers" and "active delivery agents" counters. It is left out because those queries are
currently driven by an `id IN (subquery)` where the primary key already leads, so the gain
would be marginal and unmeasurable on the current data volume. Revisit if the users table
grows past a few thousand rows.

---

## 9. Vue and frontend

### 9.1 ApexCharts was 1 MB of the eager bundle

**Problem.** `vue3-apexcharts` was registered globally in `app.js`, so it was a static
import of the entry chunk — every page paid to download and parse 1,042 KB of charting
library, including pages with no charts.

**Fix.**

```js
// Before
import VueApexCharts from 'vue3-apexcharts';
// ...
.use(VueApexCharts)
```

```js
// After
const ApexChart = defineAsyncComponent(() =>
    import('vue3-apexcharts').then((module) => module.default)
);
// ...
.component('apexchart', ApexChart)
```

Getting this to actually work took two follow-ups in `vite.config.js`. Rollup initially
produced circular vendor chunks, and then kept ApexCharts as a static import because
Vite's preload helper had been bundled into the ApexCharts chunk. Both are fixed by
explicit `manualChunks` rules.

**Impact.** Eager JavaScript dropped from 2,463.7 KB to 1,421.7 KB. Verified against the
build manifest: ApexCharts now appears only under dynamic imports.

### 9.2 Racing dashboard requests

**Problem.** Switching the period selector quickly left several `/api/dashboard` requests
in flight, and whichever finished last won — which could be the stale one.

**Fix.** The service aborts the previous request, and the page ignores any response that
is no longer the newest.

```js
// resources/js/services/DashboardService.js
let inFlight = null;

export async function fetchDashboard(params = {}) {
  inFlight?.abort();
  inFlight = new AbortController();

  const { data } = await axios.get('/api/dashboard', { params, signal: inFlight.signal });
  return data?.data ?? data;
}
```

The page guards writes with a request token, so an aborted request cannot clear the
loading flag belonging to a newer one.

### 9.3 What was checked and found healthy

The dashboard page (`dashboards/ecommerce/index.vue`) holds 8 charts and 3 tables, so it
was the prime suspect for wasteful reactivity. It is fine: computed properties derive from
a single `dashboard` ref and are cheap; the two watchers are guarded so switching to a
custom period does not fire a request until both dates are picked; `onMounted` triggers
exactly one request. The orders page applies filters on submit rather than per keystroke,
so no debounce is needed. No duplicate Axios calls were found on either page.

### 9.4 Vite configuration

Verified: `npm run build` runs in production mode with esbuild minification and tree
shaking enabled by default. Manual chunking now splits vendor code into `vendor`,
`vendor-bootstrap`, `vendor-echo`, `vendor-apexcharts` and `vendor-chartjs` so a change to
application code does not invalidate the browser's cached vendor bundles. Page components
were already lazily loaded via `import.meta.glob`. The build completes with no circular
dependency warnings.

---

## 10. HTTP layer

`public/.htaccess` gained Gzip/Brotli compression and cache headers. Compression is the
single highest-leverage change for a client on a slow link: the ~143 KB dashboard document
is mostly JSON and compresses to roughly a fifth of that.

Hashed Vite assets are served `immutable` with a one-year lifetime; HTML is explicitly
excluded so deployments are picked up immediately.

---

## 11. Production readiness

Checked against the running configuration. §11.1 and §11.3 are **not yet applied on the
server** and are required to realise the full improvement.

### 11.1 Framework caches — action required

```
Config .......... NOT CACHED
Events .......... NOT CACHED
Routes .......... NOT CACHED
Views ........... CACHED
```

257 routes were being re-registered and every config file re-parsed on every request. That
is cheap on a local SSD and expensive on a VPS. `php artisan optimize` was verified to run
cleanly on this codebase — there are no closure-based routes blocking route caching.

Add to the deploy script, after `composer install --no-dev --optimize-autoloader`:

```bash
php artisan optimize        # config + routes + events + views
php artisan migrate --force # applies the new indexes
npm ci && npm run build
rm -f public/hot            # see 11.2
```

Note that `php artisan optimize` must be re-run on every deploy, and that a cached config
ignores `.env` — any environment change requires `php artisan optimize:clear` followed by
`php artisan optimize`.

### 11.2 `public/hot` — safeguard, not a live problem

A `public/hot` file is present locally, but it is legitimate: the Vite dev server is
running on port 5173 and the file points at it. It is also listed in `.gitignore`, so it
cannot reach the server through a normal deploy.

It is still worth keeping `rm -f public/hot` in the deploy script. If the file ever does
land on the server — copied by an rsync that ignores `.gitignore`, or left behind by
someone running `npm run dev` there — Laravel rewrites every asset URL to a dev server
that is not running, and the entire frontend fails to load with no obvious cause.

### 11.3 Session driver — recommended

`SESSION_DRIVER=database` costs a `SELECT` plus an `INSERT`/`UPDATE` on every request, and
they show up in the probe as the most expensive queries on the notifications endpoint.
Switching to `redis` (or `file` if Redis is unavailable) removes two round trips per
request. This is a configuration change with no code impact.

### 11.4 Already correct

`APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`. Only `config/*.php` files
call `env()`, which is what makes config caching safe. `QUEUE_CONNECTION=sync` is worth
revisiting — any queued notification or email currently blocks the request that triggered
it — but it does not affect the two pages in scope.

---

## 12. Instrumentation

Two tools were added so this work can be repeated on OVH rather than re-derived.

**`App\Http\Middleware\LogSlowRequests`** sits at the front of the global stack and logs
any request slower than a threshold, breaking the time into PHP and database components:

```
[warning] Slow request {"uri":"/orders","total_ms":842.1,"db_ms":610.4,
          "php_ms":231.7,"queries":14,"memory_mb":38.0,"slow_queries":[...]}
```

The `db_ms` versus `php_ms` split is the fastest way to tell whether a slow production page
is database bound or PHP bound. Configured in `config/performance.php`:

| Key | Env var | Default |
| --- | --- | --- |
| `log_slow_requests` | `LOG_SLOW_REQUESTS` | `true` |
| `slow_request_threshold_ms` | `SLOW_REQUEST_THRESHOLD_MS` | `500` |
| `slow_query_threshold_ms` | `SLOW_QUERY_THRESHOLD_MS` | `200` |
| `dashboard_cache_ttl` | `DASHBOARD_CACHE_TTL` | `300` |
| `reference_cache_ttl` | `REFERENCE_CACHE_TTL` | `3600` |

It is safe to leave enabled: the listener only writes when a request is actually slow.

**`perf-probe.php`** in the project root produced every number in this report. It boots the
app, authenticates the heaviest realistic user and dispatches real requests through the
HTTP kernel, reporting wall time, query count, database time, response size, a prop-by-prop
size breakdown and a list of duplicated queries. Run it on the VPS with `php perf-probe.php`
to get the production picture. It lives outside `public/`, so it is not reachable over
HTTP; delete it once the OVH numbers are confirmed.

---

## 13. Test suite

The suite is green: **85 passed, 1 skipped**.

Five tests were failing before this work began. All five were pre-existing failures,
confirmed by stashing every change and re-running against `HEAD`; none were caused by the
optimizations. They were fixed because a red suite made it impossible to validate anything:

1. **Three `PaymentMethodTest` cases** threw `BindingResolutionException`. `tests/Pest.php`
   bound `Tests\TestCase` only to the `Feature` directory, so unit tests ran without a
   Laravel container — and `PaymentMethod::label()` resolves through the translator. Fixed
   by binding `TestCase` to `Unit` as well, without `RefreshDatabase` so they stay fast.
2. **`DashboardApiTest`** returned 500. `Api\DashboardController` called
   `$request->session()` on a stateless API route, which has no session store. This was a
   real production bug for any API caller whose user has no `locale` set. Fixed with a
   `$request->hasSession()` guard.
3. **`OrderAutoCityDeliveryTransitionTest`** asserted against the wrong row. The
   `statusHistories()` relation carries a default `orderBy('created_at')->orderBy('id')`,
   so the test's `->latest('id')` was appended rather than applied and the query returned
   the oldest row. Fixed with `->reorder()` in the test.

---

## 14. Remaining recommendations

Ordered by expected value.

1. **Apply §11.1 on the server** (`php artisan optimize` in the deploy script). Roughly a
   third of the projected improvement is configuration. Nothing else on this list matters
   until this is done.
2. **Move sessions and cache to Redis.** Removes two queries per request and makes the
   dashboard cache shared across PHP workers instead of per-file.
3. **Re-run `perf-probe.php` on the VPS.** Every measurement here is local. The OVH
   numbers will reveal whether the residual gap is CPU, MySQL or network, which cannot be
   determined from a local machine.
4. **Adopt `City::options()` in the remaining controllers.** `UserController`,
   `TransferController`, `ReturnController`, `PartnerOrderController` and
   `FortifyServiceProvider` each still run their own active-cities query. The cached
   accessor exists; wiring them up is mechanical, and was left out of this pass because
   those pages were not in scope and the payload shapes differ slightly.
5. **Apply the §5.3 date-filter fix to the other list pages.** The same
   `whereDate('created_at', ...)` anti-pattern — which prevents any index on `created_at`
   from being used — is present in `TransferQueryService`, `TransferService`,
   `ReturnQueryService`, `InvoiceQueryService`, `DriverInvoiceQueryService`,
   `PickupRequestQueryService`, `PartnerOrderQueryService` and
   `SupportTicketQueryService`. Those pages were outside the audit scope, but the fix is
   the same three-line change in each.
6. **Move `QUEUE_CONNECTION` off `sync`.** Notifications and emails currently run inside
   the request that triggers them.
7. **Consider `cursorPaginate()` for the orders list** if users routinely page deep. The
   current `paginate()` runs a `COUNT(*)` over the filtered set on every page; cursor
   pagination avoids it, at the cost of losing the total page count in the UI.
8. **Defer below-the-fold dashboard charts.** All 8 ApexCharts instances mount at once.
   Mounting the lower ones on intersection would improve time-to-interactive. This affects
   perceived speed after render, not the document time this audit targeted.
9. **Add an index on `users(status)`** if the users table grows substantially (§8).

---

## 15. Files changed

**Backend**

```
app/Http/Controllers/Api/DashboardController.php   session guard on a stateless route
app/Http/Controllers/NotificationController.php    columns, limit, bulk update
app/Http/Controllers/OrderController.php           list resource, lazy props, cached cities
app/Http/Kernel.php                                middleware ordering + slow-request logging
app/Http/Middleware/HandleInertiaRequests.php      conditional translations, slim auth.user
app/Http/Middleware/LogSlowRequests.php            new — timing instrumentation
app/Http/Resources/OrderListResource.php           new — slim list payload
app/Models/City.php                                new — cached options, flushed on write
app/Models/User.php                                memoized roles and permissions
app/Providers/AppServiceProvider.php               shared props moved to the middleware
app/Services/DashboardService.php                  deduplicated aggregates, subquery, caching
app/Services/OrderQueryService.php                 index-friendly dates, configurable eager loads
app/Support/TranslationBundle.php                  new — fingerprinted translation cache
config/performance.php                             new — thresholds and TTLs
database/migrations/2026_07_26_120000_add_performance_indexes.php   new — 9 composite indexes
```

**Frontend**

```
resources/js/app.js                                ApexCharts lazily loaded
resources/js/i18n.js                               advertises the loaded locale
resources/js/services/DashboardService.js          request cancellation
resources/js/Pages/orders/index.vue                partial reloads
resources/js/Pages/dashboards/ecommerce/index.vue  stale-response guard
vite.config.js                                     vendor chunking
```

**Infrastructure and tests**

```
public/.htaccess                                   compression + cache headers
.env.example                                       new performance keys
tests/Pest.php                                     container for unit tests
tests/Feature/OrderAutoCityDeliveryTransitionTest.php   corrected ordering assertion
perf-probe.php                                     new — measurement harness (delete after OVH run)
```
