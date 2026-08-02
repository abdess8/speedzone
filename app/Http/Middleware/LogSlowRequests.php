<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records where a slow request spent its time.
 *
 * Logs the wall time, the number of queries and how much of the wall time was
 * spent waiting on MySQL, which immediately tells you whether a slow page is
 * database bound or PHP bound.
 */
class LogSlowRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('performance.log_slow_requests')) {
            return $next($request);
        }

        $queryCount = 0;
        $queryTime = 0.0;
        $slowQueries = [];
        $slowQueryThreshold = (int) config('performance.slow_query_threshold_ms');

        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryTime, &$slowQueries, $slowQueryThreshold) {
            $queryCount++;
            $queryTime += $query->time;

            if ($slowQueryThreshold > 0 && $query->time >= $slowQueryThreshold && count($slowQueries) < 5) {
                $slowQueries[] = [
                    'ms' => round($query->time, 1),
                    'sql' => Str::limit(preg_replace('/\s+/', ' ', $query->sql), 300),
                ];
            }
        });

        $start = microtime(true);
        $response = $next($request);
        $elapsed = (microtime(true) - $start) * 1000;

        if ($elapsed >= (int) config('performance.slow_request_threshold_ms')) {
            Log::warning('Slow request', [
                'method' => $request->getMethod(),
                'uri' => $request->getRequestUri(),
                'route' => $request->route()?->getName(),
                'inertia' => $request->hasHeader('X-Inertia'),
                'user_id' => $request->user()?->id,
                'status' => $response->getStatusCode(),
                'total_ms' => round($elapsed, 1),
                'db_ms' => round($queryTime, 1),
                'php_ms' => round($elapsed - $queryTime, 1),
                'queries' => $queryCount,
                'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                'slow_queries' => $slowQueries,
            ]);
        }

        return $response;
    }
}
