<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DriverZoneService
{
    private const DEFAULT_PAGE_SIZE = 15;

    private const MAX_PAGE_SIZE = 100;

    /**
     * Base query restricted to users holding the Driver role, eager loading
     * their assigned sectors. Supports searching by driver and filtering the
     * driver list by the city/sector they serve.
     */
    public function query(Request $request): Builder
    {
        return User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('name', Role::DRIVER))
            ->with(['sectors.city'])
            ->withCount('sectors')
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(function (Builder $sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone_number', 'like', $term);
                });
            })
            ->when($request->filled('sector_id'), fn (Builder $q) => $q->whereHas(
                'sectors',
                fn (Builder $sub) => $sub->where('sectors.id', $request->integer('sector_id'))
            ))
            ->when($request->filled('city_id'), fn (Builder $q) => $q->whereHas(
                'sectors',
                fn (Builder $sub) => $sub->where('sectors.city_id', $request->integer('city_id'))
            ))
            ->orderBy('first_name')
            ->orderBy('name');
    }

    public function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PAGE_SIZE);

        if ($perPage < 1) {
            $perPage = self::DEFAULT_PAGE_SIZE;
        }

        return min($perPage, self::MAX_PAGE_SIZE);
    }

    /**
     * Guarantee that only Driver-role users can be assigned to zones.
     *
     * @throws ValidationException
     */
    public function ensureDriver(User $user): void
    {
        if (! $user->isDriver()) {
            throw ValidationException::withMessages([
                'user_id' => 'Only users with the Driver role can be assigned to delivery zones.',
            ]);
        }
    }

    /**
     * Assign sectors to a driver.
     *
     * @param  array<int, int>  $sectorIds
     */
    public function assign(User $driver, array $sectorIds, bool $replace = false): User
    {
        $this->ensureDriver($driver);

        $payload = collect($sectorIds)
            ->mapWithKeys(fn (int $id) => [$id => ['assigned_at' => now()]])
            ->all();

        if ($replace) {
            $driver->sectors()->sync($payload);
        } else {
            // Append without dropping existing assignments or touching their timestamps.
            $driver->sectors()->syncWithoutDetaching($payload);
        }

        return $driver->load(['sectors.city'])->loadCount('sectors');
    }

    public function remove(User $driver, Sector $sector): User
    {
        $this->ensureDriver($driver);

        $driver->sectors()->detach($sector->id);

        return $driver->load(['sectors.city'])->loadCount('sectors');
    }
}
