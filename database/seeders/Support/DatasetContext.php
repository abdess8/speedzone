<?php

namespace Database\Seeders\Support;

use App\Models\City;
use App\Models\Order;
use App\Models\Sector;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared state and time helpers for the Moroccan dataset generators.
 *
 * The whole dataset lives inside a single window (last month → now) and every
 * timestamp is produced by {@see self::after()}, which returns null as soon as a
 * step would land in the future. Generators treat that null as "this parcel /
 * document has not reached that stage yet", which is what naturally leaves the
 * dataset with in-flight rows instead of an implausible all-terminal history.
 */
class DatasetContext
{
    public MoroccanLocaleFaker $faker;

    /** Reference "now" for the whole run. */
    public Carbon $now;

    /** Oldest timestamp any generated row may carry. */
    public Carbon $windowStart;

    public User $admin;

    /** @var Collection<int, User> */
    public Collection $dispatchers;

    /** @var Collection<int, User> */
    public Collection $drivers;

    /** @var Collection<int, User> */
    public Collection $sellers;

    /** @var array<int, Store> Default store, keyed by seller id. */
    public array $stores = [];

    /** @var Collection<int, City> Active cities, each with its active sectors. */
    public Collection $cities;

    /** @var array<int, array<int, User>> Drivers, keyed by city id. */
    public array $driversByCity = [];

    /**
     * Cities repeated by market weight, so picking a random entry reproduces the
     * real Moroccan split (Casablanca and Rabat absorb most of the volume).
     *
     * @var array<int, City>
     */
    public array $cityPool = [];

    /** @var array<string, int> Row counters reported at the end of the run. */
    public array $stats = [];

    public function __construct(MoroccanLocaleFaker $faker, Carbon $now, int $windowDays)
    {
        $this->faker = $faker;
        $this->now = $now->copy();
        $this->windowStart = $now->copy()->subDays($windowDays)->startOfDay();
        $this->dispatchers = collect();
        $this->drivers = collect();
        $this->sellers = collect();
        $this->cities = collect();
    }

    /*
    |--------------------------------------------------------------------------
    | Time
    |--------------------------------------------------------------------------
    */

    /**
     * Next step of a lifecycle, `$minHours` to `$maxHours` after `$base`.
     *
     * Returns null when the step would fall in the future: the caller then stops
     * advancing and the row stays in its current status.
     */
    public function after(Carbon $base, int $minHours, int $maxHours): ?Carbon
    {
        $moment = $base->copy()->addMinutes(random_int($minHours * 60, $maxHours * 60));

        return $moment->lessThan($this->now) ? $moment : null;
    }

    /**
     * A working moment inside the window.
     *
     * Most of the volume is old enough for its lifecycle to have completed, and
     * a fifth lands in the last three days so the dashboard always shows fresh,
     * still-moving activity.
     */
    public function moment(): Carbon
    {
        $roll = random_int(1, 100);
        $oldest = max(15, (int) $this->windowStart->diffInDays($this->now) - 1);

        $daysAgo = match (true) {
            $roll <= 20 => random_int(0, 3),
            $roll <= 52 => random_int(4, 10),
            default => random_int(11, $oldest),
        };

        $moment = $this->now->copy()
            ->subDays($daysAgo)
            ->setTime(random_int(8, 18), (int) $this->faker->pick([0, 10, 15, 25, 30, 45, 50]));

        if ($moment->greaterThanOrEqualTo($this->now)) {
            $moment = $this->now->copy()->subHours(random_int(2, 9));
        }

        if ($moment->lessThan($this->windowStart)) {
            $moment = $this->windowStart->copy()->addHours(random_int(8, 20));
        }

        return $moment;
    }

    /**
     * Clamp a moment inside the dataset window.
     */
    public function clamp(Carbon $moment): Carbon
    {
        if ($moment->lessThan($this->windowStart)) {
            return $this->windowStart->copy()->addMinutes(random_int(30, 600));
        }

        if ($moment->greaterThan($this->now)) {
            return $this->now->copy()->subMinutes(random_int(15, 240));
        }

        return $moment;
    }

    /*
    |--------------------------------------------------------------------------
    | Actors & geography
    |--------------------------------------------------------------------------
    */

    public function city(?int $id): ?City
    {
        return $id ? $this->cities->firstWhere('id', $id) : null;
    }

    /**
     * Weight the served cities by market share.
     *
     * @param  array<string, int>  $weights
     */
    public function weightCities(array $weights, int $default = 2): void
    {
        $this->cityPool = [];

        foreach ($this->cities as $city) {
            $this->cityPool = array_merge(
                $this->cityPool,
                array_fill(0, max(1, $weights[$city->name] ?? $default), $city)
            );
        }
    }

    public function anyCity(): City
    {
        return $this->cityPool === []
            ? $this->cities->random()
            : $this->cityPool[array_rand($this->cityPool)];
    }

    public function otherCity(int $exceptCityId): City
    {
        // Keep the market weighting while guaranteeing a different destination.
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $city = $this->anyCity();

            if ($city->id !== $exceptCityId) {
                return $city;
            }
        }

        $candidates = $this->cities->where('id', '!=', $exceptCityId);

        return $candidates->isEmpty() ? $this->anyCity() : $candidates->random();
    }

    public function sector(City $city): Sector
    {
        return $city->sectors->random();
    }

    public function driverFor(City $city): ?User
    {
        $pool = $this->driversByCity[$city->id] ?? [];

        if ($pool !== []) {
            return $pool[array_rand($pool)];
        }

        return $this->drivers->isEmpty() ? null : $this->drivers->random();
    }

    public function dispatcher(): User
    {
        return $this->dispatchers->isEmpty() ? $this->admin : $this->dispatchers->random();
    }

    /**
     * Back-office user answering claims: a dispatcher most of the time, the
     * admin otherwise.
     */
    public function agent(): User
    {
        return random_int(1, 100) <= 70 ? $this->dispatcher() : $this->admin;
    }

    public function store(User $seller): ?Store
    {
        return $this->stores[$seller->id] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | Persistence helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Save a model with explicit timestamps instead of the current time.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    public function saveAt($model, Carbon $at, ?Carbon $updatedAt = null)
    {
        $model->forceFill([
            'created_at' => $at,
            'updated_at' => $updatedAt ?? $at,
        ])->save();

        return $model;
    }

    /**
     * Touch a model at a past moment without letting Eloquent stamp "now".
     *
     * @param  Model  $model
     * @param  array<string, mixed>  $attributes
     */
    public function updateAt($model, array $attributes, Carbon $at): void
    {
        $model->forceFill($attributes + ['updated_at' => $at])->save();
    }

    /**
     * Write a placeholder attachment on the public disk so the links generated
     * for receipts and claim attachments actually resolve in the UI.
     *
     * @return string Stored path.
     */
    public function storeFile(string $folder, string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) ?: 'png';
        $slug = $this->faker->slug(pathinfo($fileName, PATHINFO_FILENAME)) ?: 'fichier';
        $path = trim($folder, '/').'/'.Str::random(20).'-'.$slug.'.'.$extension;

        Storage::disk('public')->put($path, $this->placeholderContent($extension));

        return $path;
    }

    private function placeholderContent(string $extension): string
    {
        if ($extension === 'pdf') {
            return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
                ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
                ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n"
                ."trailer<</Root 1 0 R>>\n%%EOF\n";
        }

        // 1×1 transparent PNG.
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reporting
    |--------------------------------------------------------------------------
    */

    public function bump(string $key, int $by = 1): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + $by;
    }

    public function count(string $key): int
    {
        return $this->stats[$key] ?? 0;
    }

    /**
     * Orders owned by a seller, ignoring the store scope (billing-style read).
     *
     * @return Builder<Order>
     */
    public function ordersOf(User $seller)
    {
        return Order::acrossStores()->where('seller_id', $seller->id);
    }
}
