<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamMemberRequest;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\TeamService;
use App\Services\UserSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamMemberController extends Controller
{
    public function __construct(
        private readonly TeamService $team,
        private readonly UserSessionService $sessions,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('team.viewAny');

        $owner = $request->user();
        $members = $this->team->membersOf($owner);
        $activity = $this->sessions->activityFor($members->pluck('id')->all());

        return Inertia::render('team/index', [
            'members' => $members->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone_number' => $member->phone_number,
                'status' => $member->status?->value,
                'status_class' => $member->status?->badgeClass(),
                'roles' => $member->roles->map(fn (Role $role) => $role->displayName())->values(),
                'stores' => $member->stores->map(fn (Store $store) => $store->name)->values(),
                'active_sessions' => $activity[$member->id]['sessions'] ?? 0,
                'last_activity' => $activity[$member->id]['last_activity'] ?? null,
            ])->values(),
            'can' => [
                'create' => $request->user()->can('team.create'),
                'manage_roles' => $request->user()->can('team-roles.manage'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('team.create');

        return Inertia::render('team/create', $this->formOptions($request->user()));
    }

    public function store(TeamMemberRequest $request): RedirectResponse
    {
        $this->authorize('team.create');

        $member = $this->team->create($request->user(), $request->validated());

        return redirect()
            ->route('team.index')
            ->with('success', __('team.flash.created', ['name' => $member->name]));
    }

    public function edit(Request $request, User $member): Response
    {
        $this->authorize('team.update', $member);

        $member->load(['roles:id', 'stores:id']);

        return Inertia::render('team/edit', array_merge($this->formOptions($request->user()), [
            'member' => [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'phone_number' => $member->phone_number,
                'locale' => $member->locale,
                'status' => $member->status?->value,
                'role_ids' => $member->roles->pluck('id')->all(),
                'store_ids' => $member->stores->pluck('id')->all(),
            ],
            'can' => [
                'suspend' => $request->user()->can('team.suspend', $member),
            ],
        ]));
    }

    public function update(TeamMemberRequest $request, User $member): RedirectResponse
    {
        $this->authorize('team.update', $member);

        $this->team->update($request->user(), $member, $request->validated());

        return redirect()
            ->route('team.index')
            ->with('success', __('team.flash.updated', ['name' => $member->name]));
    }

    /**
     * Revoke the member's access and destroy his live sessions.
     */
    public function suspend(Request $request, User $member): RedirectResponse
    {
        $this->authorize('team.suspend', $member);

        $this->team->suspend($request->user(), $member);

        return back()->with('success', __('team.flash.suspended', ['name' => $member->name]));
    }

    public function reactivate(Request $request, User $member): RedirectResponse
    {
        $this->authorize('team.suspend', $member);

        $this->team->reactivate($request->user(), $member);

        return back()->with('success', __('team.flash.reactivated', ['name' => $member->name]));
    }

    /**
     * Stores and roles the vendor can pick from.
     *
     * @return array<string, mixed>
     */
    private function formOptions(User $owner): array
    {
        return [
            'stores' => Store::query()
                ->ownedBy($owner->id)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'is_default'])
                ->map(fn (Store $store) => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'is_default' => (bool) $store->is_default,
                ])->all(),
            'roles' => Role::query()
                ->ownedBy($owner->id)
                ->withCount('permissions')
                ->orderBy('label')
                ->get()
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'label' => $role->displayName(),
                    'permissions_count' => $role->permissions_count,
                ])->all(),
        ];
    }
}
