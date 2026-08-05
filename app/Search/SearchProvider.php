<?php

namespace App\Search;

use App\Models\User;

/**
 * One searchable object of the global search bar.
 *
 * A provider answers two questions and nothing else: may this user search me,
 * and what do I match. Row-level scoping is *not* re-implemented here — every
 * provider starts from the same query the corresponding list screen builds, so
 * search can never surface a record its owner could not open.
 */
interface SearchProvider
{
    /** Stable identifier, used as the scope value in the URL and in the UI. */
    public function key(): string;

    /** Translated name of the object, shown as the result group heading. */
    public function label(): string;

    /** Remix icon class for the group and its rows. */
    public function icon(): string;

    /**
     * Whether the object appears in the scope picker for this user at all.
     *
     * This is the read permission of the underlying screen. It is a coarse
     * gate; the per-row scope is applied by `search()`.
     */
    public function availableTo(User $user): bool;

    /**
     * @return array<int, SearchHit>
     */
    public function search(User $user, string $term, int $limit): array;
}
