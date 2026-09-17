<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\TeamRoleService;
use App\Support\AdminSellerDirectory;
use App\Support\SortableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamRoleController extends Controller
{
    public function __construct(private readonly TeamRoleService $roles) {}

    public function index(Request $request): Response
    {
        $this->authorize('team-roles.manage');

        if ($request->user()->isSuperAdmin()) {
            return $this->adminIndex($request);
        }

        $roles = Role::query()
            ->ownedBy($request->user()->accountOwnerId())
            ->withCount(['permissions', 'users'])
            ->orderBy('label')
            ->get();

        return Inertia::render('team/roles/index', [
            'roles' => $roles->map(fn (Role $role) => $this->presentRole($role))->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('team-roles.manage');

        $admin = $request->user()->isSuperAdmin();
        $seller = $admin ? AdminSellerDirectory::find($request->integer('seller_id')) : null;

        return Inertia::render('team/roles/create', [
            'permissionGroups' => $this->roles->permissionOptions(),
            'admin' => $admin,
            'sellers' => $admin ? AdminSellerDirectory::options() : [],
            'seller' => $seller ? AdminSellerDirectory::present($seller) : null,
        ]);
    }

    public function store(TeamRoleRequest $request): RedirectResponse
    {
        $this->authorize('team-roles.manage');

        $role = $this->roles->create(
            AdminSellerDirectory::actingOwner($request),
            $request->validated('label'),
            $request->validated('permissions'),
        );

        return redirect()
            ->route('team.roles.index')
            ->with('success', __('team.roles.flash.created', ['name' => $role->displayName()]));
    }

    public function edit(Request $request, Role $role): Response
    {
        $this->authorize('team-roles.update', $role);
        abort_unless($role->isCustom(), 404);

        $role->load(['permissions:id,name', 'owner:id,first_name,last_name,email']);

        return Inertia::render('team/roles/edit', [
            'role' => [
                'id' => $role->id,
                'label' => $role->displayName(),
                'permissions' => $role->permissions->map(fn (Permission $permission) => $permission->name)->all(),
                'members_count' => $role->users()->count(),
            ],
            'permissionGroups' => $this->roles->permissionOptions(),
            'admin' => $request->user()->isSuperAdmin(),
            'seller' => $role->owner ? AdminSellerDirectory::present($role->owner) : null,
        ]);
    }

    public function update(TeamRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('team-roles.update', $role);
        abort_unless($role->isCustom(), 404);

        $this->roles->update(
            $role,
            $request->validated('label'),
            $request->validated('permissions'),
        );

        return redirect()
            ->route('team.roles.index')
            ->with('success', __('team.roles.flash.updated', ['name' => $role->displayName()]));
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('team-roles.update', $role);
        abort_unless($role->isCustom(), 404);

        $name = $role->displayName();
        $this->roles->delete($role);

        return redirect()
            ->route('team.roles.index')
            ->with('success', __('team.roles.flash.deleted', ['name' => $name]));
    }

    private function adminIndex(Request $request): Response
    {
        $search = (string) $request->string('search');
        $sortable = [
            'label' => 'label',
            'seller' => 'owner_id',
            'members' => 'users_count',
            'permissions' => 'permissions_count',
        ];

        $query = Role::query()
            ->whereNotNull('owner_id')
            ->with('owner:id,first_name,last_name,email')
            ->withCount(['permissions', 'users'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('label', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhereHas('owner', function ($seller) use ($search) {
                            $seller->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when(
                $request->integer('seller_id') > 0,
                fn ($query) => $query->where('owner_id', $request->integer('seller_id'))
            );

        SortableQuery::apply($query, $request, $sortable, 'label', 'asc');

        $roles = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Role $role) => $this->presentRole($role, withSeller: true));

        return Inertia::render('team/roles/admin', [
            'roles' => $roles,
            'stats' => [
                'total' => Role::query()->whereNotNull('owner_id')->count(),
                'sellers' => Role::query()->whereNotNull('owner_id')->distinct()->count('owner_id'),
                'assigned' => Role::query()
                    ->whereNotNull('owner_id')
                    ->whereHas('users')
                    ->count(),
            ],
            'sellers' => AdminSellerDirectory::options(),
            'filters' => array_merge(
                $request->only(['search', 'seller_id']),
                SortableQuery::state($request, $sortable, 'label', 'asc')
            ),
            'can' => [
                'create' => true,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRole(Role $role, bool $withSeller = false): array
    {
        $row = [
            'id' => $role->id,
            'label' => $role->displayName(),
            'permissions_count' => $role->permissions_count,
            'members_count' => $role->users_count,
        ];

        if ($withSeller) {
            $row['seller'] = $role->owner
                ? AdminSellerDirectory::present($role->owner)
                : null;
        }

        return $row;
    }
}
