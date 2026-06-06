<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Display a listing of the roles.
     */
    public function index(): Response
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get();

        return Inertia::render('roles/index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): Response
    {
        return Inertia::render('roles/create', [
            'permissionGroups' => $this->groupedPermissions(),
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::create(['name' => $data['name']]);
        $role->permissions()->sync($data['permission_ids'] ?? []);

        return redirect()->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role): Response
    {
        $role->load('permissions:id');

        return Inertia::render('roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permission_ids' => $role->permissions->pluck('id'),
            ],
            'permissionGroups' => $this->groupedPermissions(),
        ]);
    }

    /**
     * Update the specified role in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        $role->update(['name' => $data['name']]);
        $role->permissions()->sync($data['permission_ids'] ?? []);

        return redirect()->route('roles.index')
            ->with('success', 'Role updated successfully.');
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    /**
     * Build the permission list grouped by resource for the UI.
     *
     * @return array<int, array<string, mixed>>
     */
    private function groupedPermissions(): array
    {
        return Permission::query()
            ->orderBy('resource')
            ->orderBy('name')
            ->get()
            ->groupBy('resource')
            ->map(function ($permissions, $resource) {
                return [
                    'resource' => $resource,
                    'label' => Str::headline($resource),
                    'permissions' => $permissions->map(fn (Permission $permission) => [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'action' => $permission->action,
                        'scope' => $permission->scope,
                        'type' => $permission->type,
                        'label' => $this->permissionLabel($permission),
                        'description' => $permission->description,
                    ])->values(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Produce a human-friendly label for a permission.
     */
    private function permissionLabel(Permission $permission): string
    {
        if ($permission->type === 'workflow_transition') {
            $target = Str::of($permission->name)->afterLast('to_')->replace('_', ' ')->title();

            return 'Transition → ' . $target;
        }

        $label = Str::headline($permission->action);

        if ($permission->scope) {
            $label .= ' (' . Str::title($permission->scope) . ')';
        }

        return $label;
    }
}
