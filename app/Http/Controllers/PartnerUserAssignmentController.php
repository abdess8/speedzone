<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignPartnerUsersRequest;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use App\Models\User;
use App\Services\PartnerUserAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnerUserAssignmentController extends Controller
{
    public function __construct(
        private readonly PartnerUserAssignmentService $assignments,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('partner-users.assign');

        $partners = $this->assignments->query($request)
            ->paginate($this->assignments->perPage($request))
            ->withQueryString();

        return Inertia::render('partner-assignments/index', [
            'partners' => PartnerResource::collection($partners)->response()->getData(true),
            'admins' => $this->assignments->adminOptions(),
            'filters' => $request->only(['search', 'per_page']),
            'can' => [
                'assign' => $request->user()->can('partner-users.assign'),
                'remove' => $request->user()->can('partner-users.assign'),
            ],
        ]);
    }

    public function assign(AssignPartnerUsersRequest $request, Partner $partner): RedirectResponse
    {
        $this->authorize('partner-users.assign');

        $this->assignments->syncUsers(
            $partner,
            $request->userIds(),
            $request->boolean('replace')
        );

        return back()->with('success', __('partners.assignments.saved'));
    }

    public function remove(Request $request, Partner $partner, User $user): RedirectResponse
    {
        $this->authorize('partner-users.assign');

        $this->assignments->removeUser($partner, $user);

        return back()->with('success', __('partners.assignments.removed'));
    }
}
