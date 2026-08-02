<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Support\Guides\GuideAccess;
use App\Support\Guides\GuideCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The grid pairing interactive guides with platform roles.
 *
 * A screen of its own rather than a section of the role form, because the
 * interesting state is a *column* — "who is offered the bulk import guide" —
 * and because a guide assigned to nobody behaves differently from one assigned
 * to a single role. Seen one role at a time, that distinction is invisible.
 */
class GuideAccessController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('roles.read'), 403);

        $assignments = GuideAccess::map();

        return Inertia::render('roles/guides', [
            'roles' => Role::query()
                ->system()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name])
                ->all(),
            'guides' => array_map(
                fn (array $guide) => $guide + ['role_ids' => $assignments[$guide['key']] ?? []],
                GuideCatalog::all()
            ),
            'can' => ['update' => $request->user()->hasPermission('roles.update')],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('roles.update'), 403);

        $data = $request->validate([
            'assignments' => ['present', 'array'],
            'assignments.*' => ['array'],
            // Platform roles only: a vendor's own role is not administered here,
            // it follows the Seller line of the grid.
            'assignments.*.*' => ['integer', Rule::exists('roles', 'id')->whereNull('owner_id')],
        ]);

        $assignments = [];

        // Rebuilt from the catalog rather than from the payload, so a guide the
        // administrator unchecked everywhere is saved as empty instead of being
        // left untouched because its key disappeared from the form.
        foreach (GuideCatalog::keys() as $key) {
            $assignments[$key] = $data['assignments'][$key] ?? [];
        }

        GuideAccess::sync($assignments);

        return back()->with('success', __('guides.access.saved'));
    }
}
