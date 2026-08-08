<?php

namespace App\Http\Controllers;

use App\Enums\BillingFrequency;
use App\Enums\SellerPaymentMethod;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\StoreResource;
use App\Models\City;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\DriverZoneService;
use App\Support\SortableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const DEFAULT_SORT = 'created_at';

    public function __construct(private readonly DriverZoneService $driverZones) {}

    /**
     * Display a listing of the users.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = (string) $request->string('search');

        $query = User::query()
            ->with(['role', 'city'])
            // A vendor's team members are shown folded into their owner's row
            // rather than scattered through the alphabet: side by side, nothing
            // said which shop a sub-account answered to.
            ->whereNull('parent_user_id')
            ->with([
                'teamMembers' => fn ($q) => $q
                    ->with(['roles:id,name,label', 'city:id,name'])
                    ->orderBy('first_name')
                    ->orderBy('last_name'),
            ])
            ->when($request->filled('search'), function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where(fn ($owner) => self::matchesSearch($owner, $search))
                        // A team member has no row of its own, so a search that
                        // names one has to return the account it hangs under.
                        ->orWhereHas('teamMembers', fn ($member) => self::matchesSearch($member, $search));
                });
            })
            ->when($request->filled('role'), function ($query) use ($request) {
                $query->where('role_id', $request->integer('role'));
            });

        SortableQuery::apply($query, $request, self::sortable(), self::DEFAULT_SORT);

        $users = $query->paginate(10)->withQueryString();

        $users->getCollection()->each(function (User $user) {
            $user->setAttribute('team_members', self::teamMemberRows($user));
            // The full sub-account models carry billing details the list has no
            // use for; only the flattened rows above are sent over the wire.
            $user->unsetRelation('teamMembers');
        });

        return Inertia::render('users/index', [
            'users' => $users,
            'roles' => Role::query()->system()->orderBy('name')->get(['id', 'name']),
            'filters' => array_merge(
                $request->only(['search', 'role']),
                SortableQuery::state($request, self::sortable(), self::DEFAULT_SORT),
            ),
        ]);
    }

    /**
     * The free-text clause, shared by the owner row and its team members.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     */
    private static function matchesSearch($query, string $search): void
    {
        $query->where('first_name', 'like', "%{$search}%")
            ->orWhere('last_name', 'like', "%{$search}%")
            ->orWhere('name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->orWhere('phone_number', 'like', "%{$search}%")
            ->orWhere('cin', 'like', "%{$search}%");
    }

    /**
     * Team members of a vendor account, flattened to what the list row draws.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function teamMemberRows(User $user): array
    {
        return $user->teamMembers
            ->map(fn (User $member) => [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'email' => $member->email,
                'phone_number' => $member->phone_number,
                'photo_url' => $member->photo_url,
                'city' => $member->city?->name,
                'cin' => $member->cin,
                // Team members wear the vendor's own roles, which are named per
                // account and carry a label the catalogue cannot translate.
                'role_label' => $member->roles->map->displayName()->implode(', '),
                'status' => $member->status?->value,
                'status_class' => $member->status?->badgeClass(),
                'created_at' => $member->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Columns the user list may be ordered on.
     *
     * The name shown is the first and last name joined, so the two are ordered
     * in that order rather than on the stored `name`, which is only a fallback.
     * The role sorts on its stored name: the label the reader sees is
     * translated in the browser, and ordering on it would mean shipping the
     * whole table to sort ten rows.
     *
     * @return array<string, string|array<int, mixed>>
     */
    private static function sortable(): array
    {
        return [
            'full_name' => ['first_name', 'last_name'],
            'email' => 'email',
            'phone_number' => 'phone_number',
            'city' => [
                DB::table('cities')->select('name')->whereColumn('cities.id', 'users.city_id'),
            ],
            'cin' => 'cin',
            'ice_number' => 'ice_number',
            'role' => [
                DB::table('roles')->select('name')->whereColumn('roles.id', 'users.role_id'),
            ],
            'created_at' => 'created_at',
        ];
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('users/create', [
            'roles' => Role::query()->system()->orderBy('name')->get(['id', 'name']),
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

        $stores = [];
        $teamMembers = [];

        if ($user->isAccountOwner()) {
            $teamMembers = $user->teamMembers()
                ->with(['roles:id,name,label', 'stores:id,name'])
                ->orderBy('first_name')
                ->get()
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'status' => $member->status?->value,
                    'status_class' => $member->status?->badgeClass(),
                    'roles' => $member->roles->map(fn ($role) => $role->displayName())->values(),
                    'stores' => $member->stores->pluck('name')->values(),
                ])
                ->all();
        }

        if ($user->isSeller() && ! $user->isTeamMember()) {
            $stores = StoreResource::collection(
                $user->ownedStores()
                    ->with('city')
                    // The admin is not standing on a store, but the count is
                    // spelled out as unscoped so the intent survives a refactor.
                    ->withCount(['orders' => fn ($q) => $q->withoutGlobalScope('store')])
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->get()
            )->resolve(request());
        }

        return Inertia::render('users/show', [
            'user' => $user,
            'stores' => $stores,
            'teamMembers' => $teamMembers,
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
            'roles' => Role::query()->system()->orderBy('name')->get(['id', 'name']),
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
