<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\TeamRoleService;
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

        $roles = Role::query()
            ->ownedBy($request->user()->accountOwnerId())
            ->withCount(['permissions', 'users'])
            ->orderBy('label')
            ->get();

        return Inertia::render('team/roles/index', [
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'label' => $role->displayName(),
                'permissions_count' => $role->permissions_count,
                'members_count' => $role->users_count,
            ])->all(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('team-roles.manage');

        return Inertia::render('team/roles/create', [
            'permissionGroups' => $this->roles->permissionOptions(),
        ]);
    }

    public function store(TeamRoleRequest $request): RedirectResponse
    {
        $this->authorize('team-roles.manage');

        $role = $this->roles->create(
            $request->user(),
            $request->validated('label'),
            $request->validated('permissions'),
        );

        return redirect()
            ->route('team.roles.index')
            ->with('success', __('team.roles.flash.created', ['name' => $role->displayName()]));
    }

    public function edit(Role $role): Response
    {
        $this->authorize('team-roles.update', $role);

        $role->load('permissions:id,name');

        return Inertia::render('team/roles/edit', [
            'role' => [
                'id' => $role->id,
                'label' => $role->displayName(),
                'permissions' => $role->permissions->map(fn (Permission $permission) => $permission->name)->all(),
                'members_count' => $role->users()->count(),
            ],
            'permissionGroups' => $this->roles->permissionOptions(),
        ]);
    }

    public function update(TeamRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('team-roles.update', $role);

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

        $name = $role->displayName();
        $this->roles->delete($role);

        return redirect()
            ->route('team.roles.index')
            ->with('success', __('team.roles.flash.deleted', ['name' => $name]));
    }
}
