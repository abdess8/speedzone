<?php

namespace App\Http\Controllers;

use App\Enums\BulkStatusEntityType;
use App\Models\Permission;
use App\Models\Role;
use App\Support\StatusTransitionPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration of the `source status → target status` grants.
 *
 * Reserved to platform administrators, and not by convention: the check is an
 * explicit `isSuperAdmin()` rather than a permission, because a permission that
 * unlocks the editing of permissions is a permission that can be granted away.
 *
 * The rows are derived from the transition graphs, so this screen can only ever
 * offer transitions the workflow actually allows — an administrator cannot
 * authorise a route the services would refuse to take.
 */
class StatusTransitionPermissionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $permissions = Permission::query()
            ->where('type', StatusTransitionPermissions::TYPE)
            ->with('roles:id')
            ->get()
            ->keyBy('name');

        return Inertia::render('status-transition-permissions/index', [
            'entities' => array_map(
                static fn (BulkStatusEntityType $entity): array => [
                    'value' => $entity->value,
                    'label' => $entity->label(),
                    'transitions' => array_map(
                        static fn (array $pair): array => [
                            'name' => StatusTransitionPermissions::name($entity, $pair['from'], $pair['to']),
                            'from' => $entity->statusDescriptor($pair['from']),
                            'to' => $entity->statusDescriptor($pair['to']),
                        ],
                        $entity->transitionPairs()
                    ),
                ],
                BulkStatusEntityType::cases()
            ),
            'roles' => Role::query()
                // Vendor-owned roles are out of scope: a shop's team never
                // stamps a parcel's operational status.
                ->whereNull('owner_id')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name])
                ->all(),
            // permission name → role ids holding it.
            'grants' => $permissions
                ->map(fn (Permission $permission) => $permission->roles->pluck('id')->all())
                ->all(),
        ]);
    }

    /**
     * Grant or revoke one transition for one role.
     *
     * One cell at a time rather than a whole-matrix sync: two administrators
     * on the screen at once would otherwise silently undo each other's work.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'permission' => ['required', 'string', Rule::in(StatusTransitionPermissions::names())],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->whereNull('owner_id')],
            'granted' => ['required', 'boolean'],
        ]);

        $permission = Permission::query()->where('name', $validated['permission'])->firstOrFail();
        $role = Role::query()->findOrFail($validated['role_id']);

        $validated['granted']
            ? $role->permissions()->syncWithoutDetaching([$permission->id])
            : $role->permissions()->detach($permission->id);

        return back()->with('success', __('bulk_status.permissions.saved'));
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->isSuperAdmin() === true,
            403,
            __('bulk_status.permissions.admin_only')
        );
    }
}
