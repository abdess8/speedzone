<?php

namespace App\Models\Concerns;

use App\Models\Store;
use App\Support\StoreContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Binds a model to the active store.
 *
 * Two guarantees, both automatic:
 *  - reads are filtered on the active store;
 *  - writes inherit it, so manual creation, the CSV/XLSX bulk import and the
 *    API all attribute rows to the store the actor is standing on.
 *
 * The scope is a safety net, not the only barrier: policies still check the row
 * and the `permission:` middleware still guards the URL.
 */
trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope('store', function (Builder $query): void {
            $context = app(StoreContext::class);

            if (! $context->isEnforced()) {
                return;
            }

            $query->where(
                $query->getModel()->getTable().'.store_id',
                $context->id()
            );
        });

        static::creating(function (Model $model): void {
            $context = app(StoreContext::class);

            if ($context->isEnforced() && $model->getAttribute('store_id') === null) {
                $model->setAttribute('store_id', $context->id());
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Escape hatch for cross-store reads (billing, admin exports, jobs).
     *
     * Spelled out at the call site on purpose: an unscoped query should be
     * visible in code review.
     */
    public static function acrossStores(): Builder
    {
        return static::query()->withoutGlobalScope('store');
    }
}
