<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\TeamMemberRequest;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\TeamService;
use App\Services\UserSessionService;
use App\Support\AdminSellerDirectory;
use App\Support\SortableQuery;
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

        if ($request->user()->isSuperAdmin()) {
            return $this->adminIndex($request);
        }

        $owner = $request->user();
        $members = $this->team->membersOf($owner);
        $activity = $this->sessions->activityFor($members->pluck('id')->all());

        return Inertia::render('team/index', [
            'members' => $members->map(
                fn (User $member) => $this->presentMember($member, $activity)
            )->values(),
            'can' => [
                'create' => $request->user()->can('team.create'),
                'manage_roles' => $request->user()->can('team-roles.manage'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('team.create');

        $admin = $request->user()->isSuperAdmin();
        $seller = $admin
            ? AdminSellerDirectory::find($request->integer('seller_id'))
            : $request->user();

        return Inertia::render('team/create', array_merge($this->formOptions($seller), [
            'admin' => $admin,
            'sellers' => $admin ? AdminSellerDirectory::options() : [],
            'seller' => $admin && $seller ? AdminSellerDirectory::present($seller) : null,
        ]));
    }

    public function store(TeamMemberRequest $request): RedirectResponse
    {
        $this->authorize('team.create');

        $member = $this->team->create(
            AdminSellerDirectory::actingOwner($request),
            $request->safe()->except('seller_id'),
        );

        return redirect()
            ->route('team.index')
            ->with('success', __('team.flash.created', ['name' => $member->name]));
    }

    public function edit(Request $request, User $member): Response
    {
        $this->authorize('team.update', $member);
        abort_unless($member->isTeamMember(), 404);

        $member->load(['roles:id', 'stores:id', 'parentUser:id,first_name,last_name,email']);
        $owner = AdminSellerDirectory::actingOwner($request, $member);

        return Inertia::render('team/edit', array_merge($this->formOptions($owner), [
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
            'admin' => $request->user()->isSuperAdmin(),
            'seller' => $member->parentUser ? AdminSellerDirectory::present($member->parentUser) : null,
            'can' => [
                'suspend' => $request->user()->can('team.suspend', $member),
            ],
        ]));
    }

    public function update(TeamMemberRequest $request, User $member): RedirectResponse
    {
        $this->authorize('team.update', $member);
        abort_unless($member->isTeamMember(), 404);

        $this->team->update(
            AdminSellerDirectory::actingOwner($request, $member),
            $member,
            $request->safe()->except('seller_id'),
        );

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
        abort_unless($member->isTeamMember(), 404);

        $this->team->suspend(AdminSellerDirectory::actingOwner($request, $member), $member);

        return back()->with('success', __('team.flash.suspended', ['name' => $member->name]));
    }

    public function reactivate(Request $request, User $member): RedirectResponse
    {
        $this->authorize('team.suspend', $member);
        abort_unless($member->isTeamMember(), 404);

        $this->team->reactivate(AdminSellerDirectory::actingOwner($request, $member), $member);

        return back()->with('success', __('team.flash.reactivated', ['name' => $member->name]));
    }

    private function adminIndex(Request $request): Response
    {
        $search = (string) $request->string('search');
        $sortable = [
            'name' => 'first_name',
            'seller' => 'parent_user_id',
            'status' => 'status',
            'created_at' => 'created_at',
        ];

        $query = User::query()
            ->whereNotNull('parent_user_id')
            ->with([
                'roles:id,name,label',
                'stores:id,name',
                'parentUser:id,first_name,last_name,email',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhereHas('parentUser', function ($seller) use ($search) {
                            $seller->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when(
                $request->integer('seller_id') > 0,
                fn ($query) => $query->where('parent_user_id', $request->integer('seller_id'))
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString())
            );

        SortableQuery::apply($query, $request, $sortable, 'created_at');

        $page = $query->paginate(15)->withQueryString();
        $activity = $this->sessions->activityFor($page->getCollection()->pluck('id')->all());

        $members = $page->through(
            fn (User $member) => $this->presentMember($member, $activity, withSeller: true)
        );

        return Inertia::render('team/admin', [
            'members' => $members,
            'stats' => [
                'total' => User::query()->whereNotNull('parent_user_id')->count(),
                'active' => User::query()
                    ->whereNotNull('parent_user_id')
                    ->where('status', UserStatus::Active)
                    ->count(),
                'suspended' => User::query()
                    ->whereNotNull('parent_user_id')
                    ->where('status', UserStatus::Suspended)
                    ->count(),
            ],
            'sellers' => AdminSellerDirectory::options(),
            'statuses' => [
                ['value' => UserStatus::Active->value, 'label' => __('user_statuses.'.UserStatus::Active->value)],
                ['value' => UserStatus::Suspended->value, 'label' => __('user_statuses.'.UserStatus::Suspended->value)],
            ],
            'filters' => array_merge(
                $request->only(['search', 'seller_id', 'status']),
                SortableQuery::state($request, $sortable, 'created_at')
            ),
            'can' => [
                'create' => true,
                'manage_roles' => true,
            ],
        ]);
    }

    /**
     * @param  array<int, array{sessions: int, last_activity: int|null}>  $activity
     * @return array<string, mixed>
     */
    private function presentMember(User $member, array $activity, bool $withSeller = false): array
    {
        $row = [
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
        ];

        if ($withSeller) {
            $row['seller'] = $member->parentUser
                ? AdminSellerDirectory::present($member->parentUser)
                : null;
        }

        return $row;
    }

    /**
     * Stores and roles the vendor can pick from.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?User $owner): array
    {
        if ($owner === null || $owner->isSuperAdmin()) {
            return [
                'stores' => [],
                'roles' => [],
            ];
        }

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
