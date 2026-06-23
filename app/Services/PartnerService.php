<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PartnerAuthType;
use App\Enums\PartnerOrderField;
use App\Enums\PartnerUpdateField;
use App\Models\City;
use App\Models\Partner;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PartnerService
{
    private const DEFAULT_PAGE_SIZE = 15;

    private const MAX_PAGE_SIZE = 100;

    /**
     * Build a filtered, searchable partner query (shared by web + API).
     */
    public function query(Request $request): Builder
    {
        return Partner::query()
            ->with('receptionCity')
            ->withCount('orders')
            ->when(
                $request->filled('search'),
                fn (Builder $q) => $q->where(function (Builder $sub) use ($request) {
                    $term = '%'.$request->string('search').'%';
                    $sub->where('name', 'like', $term)
                        ->orWhere('ice_number', 'like', $term);
                })
            )
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $q->where('is_active', $request->string('status') === 'active')
            )
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
     * Form dropdown/checkbox data loaded from the database (never hardcoded).
     *
     * @return array{cities: array<int, array<string, mixed>>, order_statuses: array<int, array<string, mixed>>, order_fields: array<int, array<string, mixed>>, update_fields: array<int, array<string, mixed>>}
     */
    public function formOptions(): array
    {
        return [
            'cities' => $this->citiesWithSectors(),
            'order_statuses' => OrderStatus::options(),
            'order_fields' => PartnerOrderField::options(),
            'update_fields' => PartnerUpdateField::options(),
            'auth_types' => PartnerAuthType::options(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Partner
    {
        return DB::transaction(function () use ($data): Partner {
            [$relations, $data] = $this->extractRelations($data);

            if (($data['client_secret'] ?? null) === '') {
                unset($data['client_secret']);
            }

            $partner = Partner::create($data);
            $this->syncRelations($partner, $relations);

            return $partner;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Partner $partner, array $data): Partner
    {
        return DB::transaction(function () use ($partner, $data): Partner {
            [$relations, $data] = $this->extractRelations($data);

            if (! array_key_exists('client_secret', $data) || $data['client_secret'] === null || $data['client_secret'] === '') {
                unset($data['client_secret']);
            } else {
                $data['access_token'] = null;
                $data['token_expires_at'] = null;
            }

            if ($this->authCredentialsChanged($partner, $data)) {
                $data['access_token'] = null;
                $data['token_expires_at'] = null;
            }

            $partner->update($data);
            $this->syncRelations($partner, $relations);

            return $partner->refresh();
        });
    }

    /**
     * Build a transient partner model for connection tests from unsaved form data.
     *
     * @param  array<string, mixed>  $data
     */
    public function makeConnectionProbe(Partner $partner, array $data): Partner
    {
        $probe = $partner->replicate();
        $probe->id = $partner->id;
        $probe->exists = $partner->exists;

        foreach ([
            'api_base_url',
            'auth_type',
            'client_id',
            'endpoint_statuses',
            'endpoint_login',
            'api_key_header',
            'login_username_field',
            'login_password_field',
            'login_token_field',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $probe->{$field} = $data[$field];
            }
        }

        if (filled($data['client_secret'] ?? null)) {
            $probe->client_secret = $data['client_secret'];
        }

        return $probe;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function authCredentialsChanged(Partner $partner, array $data): bool
    {
        $watch = [
            'auth_type',
            'client_id',
            'endpoint_login',
            'api_key_header',
            'login_username_field',
            'login_password_field',
        ];

        foreach ($watch as $field) {
            if (array_key_exists($field, $data) && $data[$field] != $partner->{$field}) {
                return true;
            }
        }

        return false;
    }

    /**
     * A partner cannot be removed while it still has orders attached.
     */
    public function canDelete(Partner $partner): bool
    {
        return ! $partner->orders()->exists();
    }

    public function delete(Partner $partner): void
    {
        $this->deleteLogo($partner);
        $partner->delete();
    }

    public function deleteLogo(Partner $partner): void
    {
        $path = $partner->getRawOriginal('logo_url');

        if ($path && ! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://') && ! str_starts_with($path, '/')) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function citiesWithSectors(): array
    {
        return City::query()
            ->active()
            ->with(['activeSectors' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (City $city) => [
                'id' => $city->id,
                'name' => $city->name,
                'sectors' => $city->activeSectors
                    ->map(fn (Sector $sector) => [
                        'id' => $sector->id,
                        'name' => $sector->name,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function extractRelations(array $data): array
    {
        return [
            [
                'city_ids' => Arr::pull($data, 'city_ids', []),
                'sector_ids' => Arr::pull($data, 'sector_ids', []),
                'status_mappings' => Arr::pull($data, 'status_mappings', []),
                'field_mappings' => Arr::pull($data, 'field_mappings', []),
                'update_field_mappings' => Arr::pull($data, 'update_field_mappings', []),
            ],
            $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $relations
     */
    private function syncRelations(Partner $partner, array $relations): void
    {
        $cityIds = $relations['city_ids'] ?? [];
        $sectorIds = $relations['sector_ids'] ?? [];

        $this->assertSectorsBelongToCities($sectorIds, $cityIds);

        $partner->cities()->sync($cityIds);
        $partner->sectors()->sync($sectorIds);
        $this->syncStatusMappings($partner, $relations['status_mappings'] ?? []);
        $this->syncFieldMappings($partner, $relations['field_mappings'] ?? []);
        $this->syncUpdateFieldMappings($partner, $relations['update_field_mappings'] ?? []);
    }

    /**
     * @param  array<int, int>  $sectorIds
     * @param  array<int, int>  $cityIds
     */
    private function assertSectorsBelongToCities(array $sectorIds, array $cityIds): void
    {
        if ($sectorIds === []) {
            return;
        }

        $validCount = Sector::query()
            ->active()
            ->whereIn('id', $sectorIds)
            ->whereIn('city_id', $cityIds)
            ->count();

        if ($validCount !== count($sectorIds)) {
            throw ValidationException::withMessages([
                'sector_ids' => 'Selected sectors must belong to the delegated cities.',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappings
     */
    private function syncStatusMappings(Partner $partner, array $mappings): void
    {
        $partner->statusMappings()->delete();

        foreach ($this->dedupeMappings($mappings, 'speedzone_status', 'partner_status') as $speedzoneStatus => $partnerStatus) {
            $partner->statusMappings()->create([
                'speedzone_status' => $speedzoneStatus,
                'partner_status' => $partnerStatus,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappings
     */
    private function syncFieldMappings(Partner $partner, array $mappings): void
    {
        $partner->fieldMappings()->delete();

        foreach ($this->dedupeMappings($mappings, 'speedzone_field', 'partner_field') as $speedzoneField => $partnerField) {
            $partner->fieldMappings()->create([
                'speedzone_field' => $speedzoneField,
                'partner_field' => $partnerField,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappings
     */
    private function syncUpdateFieldMappings(Partner $partner, array $mappings): void
    {
        $partner->updateFieldMappings()->delete();

        foreach ($this->dedupeMappings($mappings, 'speedzone_field', 'partner_field') as $speedzoneField => $partnerField) {
            $partner->updateFieldMappings()->create([
                'speedzone_field' => $speedzoneField,
                'partner_field' => $partnerField,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappings
     * @return array<string, string>
     */
    private function dedupeMappings(array $mappings, string $keyField, string $valueField): array
    {
        $deduped = [];

        foreach ($mappings as $mapping) {
            $key = $mapping[$keyField] ?? null;
            $value = trim((string) ($mapping[$valueField] ?? ''));

            if (! $key || $value === '') {
                continue;
            }

            $deduped[(string) $key] = $value;
        }

        return $deduped;
    }
}
