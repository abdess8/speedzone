<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StoreService
{
    private const LOGO_DIRECTORY = 'store-logos';

    /**
     * Create a shop and immediately grant its owner access to it.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $owner, array $data, ?UploadedFile $logo = null): Store
    {
        return DB::transaction(function () use ($owner, $data, $logo): Store {
            $isFirst = ! Store::query()->ownedBy($owner->id)->exists();

            $store = new Store($data);
            $store->owner_id = $owner->id;
            // The very first shop is the default one, otherwise the user would
            // land on no store at all after logging in.
            $store->is_default = $isFirst || (bool) ($data['is_default'] ?? false);

            if ($logo) {
                $store->logo_path = $this->storeLogo($logo);
            }

            $store->save();

            if ($store->is_default) {
                $this->demoteOtherDefaults($store);
            }

            $store->users()->syncWithoutDetaching([$owner->id]);
            $owner->forgetStoreMemo();

            return $store;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Store $store, array $data, ?UploadedFile $logo = null): Store
    {
        return DB::transaction(function () use ($store, $data, $logo): Store {
            if ($logo) {
                $this->deleteLogo($store);
                $data['logo_path'] = $this->storeLogo($logo);
            }

            $store->fill($data)->save();

            if ($store->is_default) {
                $this->demoteOtherDefaults($store);
            }

            return $store->refresh();
        });
    }

    /**
     * Create the shop that every new vendor account starts with.
     *
     * Called on approval rather than on registration: an account that never
     * gets approved should not leave a store behind.
     */
    public function createDefaultFor(User $seller): ?Store
    {
        if (Store::query()->ownedBy($seller->id)->exists()) {
            return null;
        }

        return $this->create($seller, [
            'name' => $seller->default_store_name ?: $seller->full_name,
            'logo_path' => $seller->default_store_logo,
            'contact_name' => $seller->full_name,
            'contact_phone' => $seller->phone_number,
            'contact_email' => $seller->email,
            'city_id' => $seller->city_id,
            'address' => $seller->address,
            'pickup_address_1' => $seller->pickup_address_1,
            'pickup_address_2' => $seller->pickup_address_2,
            'is_default' => true,
        ]);
    }

    /**
     * Whether the shop can be archived.
     *
     * A store holding orders is never deleted: its labels, invoices and returns
     * would lose the name and logo they were printed with.
     */
    public function canDelete(Store $store): bool
    {
        if ($store->is_default) {
            return false;
        }

        return ! $store->orders()->withoutGlobalScope('store')->exists();
    }

    public function delete(Store $store): void
    {
        DB::transaction(function () use ($store): void {
            $store->users()->detach();
            $store->delete();
        });
    }

    private function demoteOtherDefaults(Store $store): void
    {
        Store::query()
            ->ownedBy($store->owner_id)
            ->whereKeyNot($store->id)
            ->update(['is_default' => false]);
    }

    private function storeLogo(UploadedFile $logo): string
    {
        return $logo->store(self::LOGO_DIRECTORY, 'public');
    }

    private function deleteLogo(Store $store): void
    {
        if ($store->logo_path) {
            Storage::disk('public')->delete($store->logo_path);
        }
    }
}
