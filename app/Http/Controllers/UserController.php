<?php

namespace App\Http\Controllers;

use App\Enums\BillingFrequency;
use App\Enums\SellerPaymentMethod;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\City;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\DriverZoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly DriverZoneService $driverZones) {}

    /**
     * Display a listing of the users.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with(['role', 'city'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('cin', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('role'), function ($query) use ($request) {
                $query->where('role_id', $request->integer('role'));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'role']),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('users/create', [
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'cities' => City::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'sectors' => $this->sectorOptions(),
            'billingFrequencies' => BillingFrequency::options(),
            'paymentMethods' => SellerPaymentMethod::options(),
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();

        $data['name'] = trim($data['first_name'].' '.$data['last_name']);
        $data['password'] = Hash::make($data['password']);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('profile-photos', 'public');
            $data['profile_photo_path'] = $path;
            $data['photo'] = $path;
        }

        $data['attached_files'] = $this->storeAttachedFiles($request);

        $data['billing_enabled'] = $request->boolean('billing_enabled');
        $data = $this->normaliseBillingDate($data);
        $data = $this->storeBillingAttachments($request, $data);

        unset($data['sector_ids']);

        $user = User::create($data);
        if (! empty($data['role_id'])) {
            $user->roles()->sync([$data['role_id']]);
        }

        $this->syncDriverSectors($request, $user, $data['role_id'] ?? null);

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        $user->load(['role', 'city']);

        if ($user->isDriver()) {
            $user->load(['sectors.city']);
        }

        return Inertia::render('users/show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $user->load(['role', 'city', 'sectors.city']);

        return Inertia::render('users/edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'cities' => City::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'sectors' => $this->sectorOptions(),
            'billingFrequencies' => BillingFrequency::options(),
            'paymentMethods' => SellerPaymentMethod::options(),
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();

        $data['name'] = trim($data['first_name'].' '.$data['last_name']);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($request->hasFile('photo')) {
            $this->deleteProfilePhotoFiles($user);
            $path = $request->file('photo')->store('profile-photos', 'public');
            $data['profile_photo_path'] = $path;
            $data['photo'] = $path;
        } else {
            unset($data['photo'], $data['profile_photo_path']);
        }

        $data['attached_files'] = $this->resolveAttachedFiles($request, $user);

        unset($data['removed_files']);

        $data['billing_enabled'] = $request->boolean('billing_enabled');
        $data = $this->normaliseBillingDate($data);
        $data = $this->storeBillingAttachments($request, $data, $user);

        unset($data['sector_ids']);

        $user->update($data);
        if (array_key_exists('role_id', $data) && ! empty($data['role_id'])) {
            $this->authorize('assignRoles', User::class);
            $user->roles()->sync([$data['role_id']]);
        }

        $this->syncDriverSectors($request, $user, $data['role_id'] ?? null);

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Called directly rather than through the Gate: the "never delete your
        // own account" invariant must apply to super admins too.
        abort_unless(app(UserPolicy::class)->evaluateDelete($request->user(), $user), 403);

        $this->deleteProfilePhotoFiles($user);

        foreach ($user->attached_files ?? [] as $file) {
            if (! empty($file['path'])) {
                Storage::disk('public')->delete($file['path']);
            }
        }

        foreach (['rib_attachment', 'cin_front_attachment', 'cin_back_attachment'] as $field) {
            if ($user->{$field}) {
                Storage::disk('public')->delete($user->{$field});
            }
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Persist single-file billing attachments (RIB, CIN front/back). On update,
     * a newly uploaded file replaces (and deletes) the previous one; absent
     * fields keep the existing value untouched.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storeBillingAttachments(Request $request, array $data, ?User $user = null): array
    {
        foreach (['rib_attachment', 'cin_front_attachment', 'cin_back_attachment'] as $field) {
            if ($request->hasFile($field)) {
                if ($user && $user->{$field}) {
                    Storage::disk('public')->delete($user->{$field});
                }
                $data[$field] = $request->file($field)->store('users/billing', 'public');
            } else {
                // Don't overwrite an existing path with null.
                unset($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Turn empty billing dates into null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normaliseBillingDate(array $data): array
    {
        if (array_key_exists('next_billing_date', $data) && empty($data['next_billing_date'])) {
            $data['next_billing_date'] = null;
        }

        return $data;
    }

    /**
     * Store newly uploaded attached files and return their metadata.
     *
     * @return array<int, array<string, string>>
     */
    private function storeAttachedFiles(Request $request): array
    {
        $stored = [];

        foreach ((array) $request->file('attached_files', []) as $file) {
            $stored[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $file->store('users/documents', 'public'),
            ];
        }

        return $stored;
    }

    /**
     * Merge existing attached files (minus removed ones) with newly uploaded files.
     *
     * @return array<int, array<string, string>>
     */
    private function resolveAttachedFiles(Request $request, User $user): array
    {
        $removed = (array) $request->input('removed_files', []);

        $existing = collect($user->attached_files ?? [])
            ->reject(function ($file) use ($removed) {
                $shouldRemove = in_array($file['path'] ?? '', $removed, true);
                if ($shouldRemove && ! empty($file['path'])) {
                    Storage::disk('public')->delete($file['path']);
                }

                return $shouldRemove;
            })
            ->values()
            ->all();

        return array_merge($existing, $this->storeAttachedFiles($request));
    }

    /**
     * Remove stored profile photo file(s) for a user.
     */
    private function deleteProfilePhotoFiles(User $user): void
    {
        $paths = array_unique(array_filter([
            $user->profile_photo_path,
            $user->photo,
        ]));

        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sectorOptions(): array
    {
        return Sector::query()
            ->active()
            ->with('city:id,name')
            ->orderBy('name')
            ->get(['id', 'city_id', 'name', 'delivery_price'])
            ->map(fn (Sector $sector) => [
                'id' => $sector->id,
                'city_id' => $sector->city_id,
                'city_name' => $sector->city?->name,
                'name' => $sector->name,
                'delivery_price' => (float) $sector->delivery_price,
            ])
            ->all();
    }

    private function syncDriverSectors(Request $request, User $user, mixed $roleId): void
    {
        $role = $roleId ? Role::query()->find($roleId) : null;

        if ($role?->name === Role::DRIVER) {
            $sectorIds = array_values(array_unique(array_map(
                'intval',
                (array) $request->input('sector_ids', [])
            )));

            $this->driverZones->assign($user, $sectorIds, replace: true);

            return;
        }

        $user->sectors()->detach();
    }
}
