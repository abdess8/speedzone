<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Http\Request;

/**
 * ORDER BY driven by the `sort` and `direction` query parameters.
 *
 * The public sort key is never used as a column name. It is looked up in a
 * whitelist the caller owns, which both keeps raw input out of the SQL and lets
 * a column be renamed without breaking every bookmarked URL that sorts by it.
 * An unknown key falls back to the default instead of erroring: a stale link is
 * not worth a 500.
 */
class SortableQuery
{
    /**
     * Sort the query in place.
     *
     * @param  array<string, string|array<int, string>>  $sortable  public key => column, or columns applied in order
     */
    public static function apply(
        BuilderContract $query,
        Request $request,
        array $sortable,
        string $default,
        string $defaultDirection = 'desc',
        ?string $tieBreaker = 'id'
    ): void {
        ['sort' => $sort, 'direction' => $direction] = self::state(
            $request,
            $sortable,
            $default,
            $defaultDirection
        );

        foreach ((array) ($sortable[$sort] ?? $sort) as $column) {
            $query->orderBy($column, $direction);
        }

        // Without it, two rows sharing a value can swap places between pages and
        // the same row shows up twice — or never.
        if ($tieBreaker !== null && $tieBreaker !== $sort) {
            $query->orderBy($tieBreaker, 'desc');
        }
    }

    /**
     * The sort actually applied, to echo back so the header can show its arrow.
     *
     * @param  array<string, string|array<int, string>>  $sortable
     * @return array{sort: string, direction: string}
     */
    public static function state(
        Request $request,
        array $sortable,
        string $default,
        string $defaultDirection = 'desc'
    ): array {
        // Read as a string only when it is one: `?sort[]=x` would otherwise
        // raise an array-to-string warning, which Laravel turns into a 500 —
        // exactly what the fallback below exists to avoid.
        $sort = $request->input('sort', $default);
        $direction = $request->input('direction', $defaultDirection);

        if (! is_string($sort) || ! array_key_exists($sort, $sortable)) {
            $sort = $default;
        }

        $direction = is_string($direction) && strtolower($direction) === 'asc'
            ? 'asc'
            : 'desc';

        return ['sort' => $sort, 'direction' => $direction];
    }
}
