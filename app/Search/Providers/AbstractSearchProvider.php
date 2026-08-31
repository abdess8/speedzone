<?php

namespace App\Search\Providers;

use App\Models\User;
use App\Search\SearchProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Shared plumbing for the search providers: term escaping and the value
 * formatting used by the preview panel.
 */
abstract class AbstractSearchProvider implements SearchProvider
{
    /**
     * A LIKE pattern that treats the user's `%` and `_` as literals.
     *
     * Without this a lone `%` matches the whole table, which on a term typed
     * one keystroke at a time is a full scan per keystroke.
     */
    protected function like(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
    }

    /**
     * The list screens build their query from the incoming request; search has
     * no filters to pass, so it hands them an empty one and keeps only the
     * authorization scope.
     */
    protected function unfiltered(): Request
    {
        return new Request;
    }

    /**
     * Any-of check, matching the `permission:` middleware guarding the screen
     * the provider searches.
     *
     * @param  array<int, string>  $permissions
     */
    protected function canAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep only the rows this user could actually open.
     *
     * Objects whose list screen has no query service carry their row rule in a
     * policy instead — a collector may read the shipments on his round, a
     * vendor only his own. Asking the policy is what keeps search from
     * advertising a record that answers 403 on click, and it cannot drift from
     * the detail page because it *is* the detail page's rule.
     *
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $models
     * @return array<int, TModel>
     */
    protected function readable(Collection $models, User $user, int $limit): array
    {
        return $models
            ->filter(fn (Model $model) => $user->can('view', $model))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * How many rows to read before the policy filter, so that a handful of
     * unreadable ones do not empty the group.
     */
    protected function overfetch(int $limit): int
    {
        return $limit * 4;
    }

    protected function money(int|float|string|null $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return number_format((float) $amount, 2, ',', ' ').' '.__('common.currency_mad');
    }

    protected function date(mixed $value): ?string
    {
        return $value?->translatedFormat('d M Y') ?? null;
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function whereAnyLike(Builder $query, array $columns, string $term): Builder
    {
        $like = $this->like($term);

        return $query->where(function (Builder $sub) use ($columns, $like): void {
            foreach ($columns as $column) {
                $sub->orWhere($column, 'like', $like);
            }
        });
    }
}
