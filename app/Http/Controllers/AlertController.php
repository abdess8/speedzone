<?php

namespace App\Http\Controllers;

use App\Enums\AlertFormat;
use App\Enums\AlertType;
use App\Http\Requests\StoreAlertRequest;
use App\Http\Requests\UpdateAlertRequest;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    private const DEFAULT_PAGE_SIZE = 15;

    private const MAX_PAGE_SIZE = 100;

    /** Matches shown by the user picker before the reader narrows the search. */
    private const USER_SEARCH_LIMIT = 20;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Alert::class);

        $alerts = $this->query($request)
            ->with('author:id,name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('alerts/index', [
            'alerts' => AlertResource::collection($alerts)->response()->getData(true),
            'filters' => $request->only(['search', 'type', 'display_format', 'status', 'per_page']),
            'types' => AlertType::options(),
            'formats' => AlertFormat::options(),
            // The table spells the audience out in words, which means turning
            // the stored city identifiers back into names.
            'cities' => $this->cityOptions(),
            'can' => $this->abilities($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Alert::class);

        return Inertia::render('alerts/create', $this->formOptions());
    }

    public function store(StoreAlertRequest $request): RedirectResponse
    {
        $this->authorize('create', Alert::class);

        $alert = Alert::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('alerts.index')
            ->with('success', __('alerts.flash.created', ['title' => $alert->title]));
    }

    public function edit(Request $request, Alert $alert): Response
    {
        $this->authorize('update', $alert);

        return Inertia::render('alerts/edit', [
            // resolve() drops the `data` envelope a JsonResource adds by
            // default, which the form would otherwise have to reach through.
            'alert' => AlertResource::make($alert)->resolve($request),
            // The picker only ever loads a page of users at a time, so the
            // people already targeted have to travel with the alert or they
            // would show up as bare identifiers.
            'selectedUsers' => $this->usersByIds($alert->target_user_ids ?? []),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateAlertRequest $request, Alert $alert): RedirectResponse
    {
        $this->authorize('update', $alert);

        $alert->update($request->validated());

        return redirect()
            ->route('alerts.index')
            ->with('success', __('alerts.flash.updated', ['title' => $alert->title]));
    }

    /**
     * Flips the alert on or off without opening the form.
     */
    public function toggle(Request $request, Alert $alert): RedirectResponse
    {
        $this->authorize('update', $alert);

        if ($alert->hasExpired()) {
            return back()->with('error', __('alerts.flash.expired_cannot_enable'));
        }

        $alert->update(['is_active' => ! $alert->is_active]);

        return back()->with('success', __(
            $alert->is_active ? 'alerts.flash.enabled' : 'alerts.flash.disabled',
            ['title' => $alert->title]
        ));
    }

    public function destroy(Request $request, Alert $alert): RedirectResponse
    {
        $this->authorize('delete', $alert);

        $title = $alert->title;
        $alert->delete();

        return redirect()
            ->route('alerts.index')
            ->with('success', __('alerts.flash.deleted', ['title' => $title]));
    }

    /**
     * Feeds the "specific users" picker.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $this->authorize('create', Alert::class);

        $term = trim((string) $request->string('q'));

        $users = User::query()
            ->when($term !== '', fn (Builder $query) => $query->where(
                fn (Builder $sub) => $sub
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
            ))
            ->orderBy('name')
            ->limit(self::USER_SEARCH_LIMIT)
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $this->userOptions($users)]);
    }

    /**
     * @return Builder<Alert>
     */
    private function query(Request $request): Builder
    {
        return Alert::query()
            ->when($request->filled('search'), fn (Builder $query) => $query->where(
                'title',
                'like',
                '%'.$request->string('search').'%'
            ))
            ->when(
                $request->filled('type'),
                fn (Builder $query) => $query->where('type', $request->string('type'))
            )
            ->when(
                $request->filled('display_format'),
                fn (Builder $query) => $query->where('display_format', $request->string('display_format'))
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $this->applyStatus($query, (string) $request->string('status'))
            )
            ->orderByDesc('created_at');
    }

    /**
     * @param  Builder<Alert>  $query
     * @return Builder<Alert>
     */
    private function applyStatus(Builder $query, string $status): Builder
    {
        $now = Carbon::now();

        return match ($status) {
            'active' => $query->where('is_active', true)->where('end_date', '>', $now),
            'expired' => $query->where('end_date', '<=', $now),
            'disabled' => $query->where('is_active', false)->where('end_date', '>', $now),
            default => $query,
        };
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PAGE_SIZE);

        if ($perPage < 1) {
            $perPage = self::DEFAULT_PAGE_SIZE;
        }

        return min($perPage, self::MAX_PAGE_SIZE);
    }

    /**
     * Everything the create and edit forms need to render their pickers.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'types' => AlertType::options(),
            'formats' => AlertFormat::options(),
            'roles' => Role::query()
                ->system()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Role $role) => [
                    'value' => $role->name,
                    'label' => trans('roles.'.$role->name),
                ])
                ->all(),
            'cities' => $this->cityOptions(),
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function cityOptions(): array
    {
        return City::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (City $city) => ['value' => $city->id, 'label' => $city->name])
            ->all();
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, array{value: int, label: string}>
     */
    private function usersByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->userOptions(
            User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'email'])
        );
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int, array{value: int, label: string}>
     */
    private function userOptions($users): array
    {
        return $users->map(fn (User $user) => [
            'value' => $user->id,
            'label' => $user->email ? "{$user->name} ({$user->email})" : $user->name,
        ])->all();
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();

        return [
            'create' => $user->can('create', Alert::class),
            'update' => $user->hasPermission('alerts.update'),
            'delete' => $user->hasPermission('alerts.delete'),
        ];
    }
}
