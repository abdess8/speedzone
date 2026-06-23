<?php

namespace App\Http\Controllers;

use App\Enums\SupportObjectType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Http\Requests\AssignSupportTicketRequest;
use App\Http\Requests\StoreSupportMessageRequest;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\User;
use App\Policies\SupportTicketPolicy;
use App\Services\SupportTicketQueryService;
use App\Services\SupportTicketService;
use App\Support\SupportPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportTicketService $tickets,
        private readonly SupportTicketQueryService $ticketQuery,
        private readonly SupportTicketPolicy $ticketPolicy,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SupportTicket::class);

        $tickets = $this->ticketQuery->build($request, $request->user())
            ->withCount('messages')
            ->paginate($this->ticketQuery->perPage($request))
            ->withQueryString();

        $this->attachObjectSummaries(collect($tickets->items()));

        return Inertia::render('support-tickets/index', [
            'tickets' => SupportTicketResource::collection($tickets)->response()->getData(true),
            'filters' => $request->only([
                'reference', 'subject', 'seller', 'assigned_to', 'status', 'category',
                'created_from', 'created_to', 'sort', 'direction', 'per_page',
            ]),
            'filterOptions' => [
                'statuses' => SupportTicketStatus::options(),
                'categories' => SupportTicketCategory::options(),
                'agents' => $this->agentOptions($request->user()),
                'pageSizes' => [10, 25, 50, 100],
            ],
            'can' => $this->abilities($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', SupportTicket::class);

        return Inertia::render('support-tickets/create', [
            'categories' => SupportTicketCategory::options(),
            'objectTypes' => SupportObjectType::options(),
            'prefill' => [
                'object_type' => $request->input('object_type'),
                'object_id' => $request->filled('object_id') ? (int) $request->input('object_id') : null,
            ],
            'can' => $this->abilities($request),
        ]);
    }

    public function store(StoreSupportTicketRequest $request): RedirectResponse
    {
        $this->authorize('create', SupportTicket::class);

        $ticket = $this->tickets->createTicket(
            $request->user(),
            $request->only(['category', 'object_type', 'object_id', 'subject', 'message']),
            $request->file('attachment'),
        );

        return redirect()
            ->route('support-tickets.show', $ticket)
            ->with('success', __('support_tickets.created', ['reference' => $ticket->reference]));
    }

    public function show(Request $request, SupportTicket $supportTicket): Response
    {
        $this->authorize('view', $supportTicket);

        $supportTicket->load([
            'creator.city',
            'assignee',
            'closedBy',
            'attachments',
            'messages.sender',
        ]);

        $supportTicket->object_summary = $this->buildObjectSummary($supportTicket);

        return Inertia::render('support-tickets/show', [
            'ticket' => SupportTicketResource::make($supportTicket)->resolve($request),
            'agents' => $this->agentOptions($request->user()),
            'can' => array_merge($this->abilities($request), $this->ticketActionAbilities($request->user(), $supportTicket)),
        ]);
    }

    public function storeMessage(StoreSupportMessageRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        if ($denied = $this->denyUnlessTicketAction('reply', $request->user(), $supportTicket)) {
            return $denied;
        }

        $this->tickets->addMessage(
            $supportTicket,
            $request->user(),
            $request->input('message'),
            $request->file('attachment'),
        );

        return back()->with('success', __('support_tickets.message_sent'));
    }

    public function assign(AssignSupportTicketRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        if ($denied = $this->denyUnlessTicketAction('assign', $request->user(), $supportTicket)) {
            return $denied;
        }

        $agent = $request->filled('assigned_to')
            ? User::query()->find($request->integer('assigned_to'))
            : null;

        $this->tickets->assign($supportTicket, $agent);

        return back()->with('success', __('support_tickets.assigned'));
    }

    public function updateStatus(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        if ($denied = $this->denyUnlessTicketAction('updateStatus', $request->user(), $supportTicket)) {
            return $denied;
        }

        $validated = $request->validate([
            'status' => ['required', 'string', \Illuminate\Validation\Rule::in(SupportTicketStatus::values())],
        ]);

        $this->tickets->changeStatus($supportTicket, SupportTicketStatus::from($validated['status']), $request->user());

        return back()->with('success', __('support_tickets.status_updated'));
    }

    public function close(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        if ($denied = $this->denyUnlessTicketAction('close', $request->user(), $supportTicket)) {
            return $denied;
        }

        $this->tickets->close($supportTicket, $request->user());

        return back()->with('success', __('support_tickets.closed'));
    }

    /**
     * AJAX: list the seller's records of a given object type for the create form.
     */
    public function relatedObjects(Request $request): JsonResponse
    {
        $this->authorize('create', SupportTicket::class);

        $type = SupportObjectType::tryFrom((string) $request->input('object_type'));

        if (! $type) {
            return response()->json(['data' => []]);
        }

        $user = $request->user();
        $isStaff = $user->hasPermission(SupportPermissions::READ_ALL)
            || $user->hasPermission(SupportPermissions::MANAGE);

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = $type->modelClass();
        $reference = $type->referenceColumn();

        $query = $model::query()->latest('id');

        if (! $isStaff) {
            $query->where($type->ownerColumn(), $user->id);
        }

        if ($search = $request->input('search')) {
            $query->where($reference, 'like', "%{$search}%");
        }

        $records = $query->limit(50)->get(['id', $reference]);

        return response()->json([
            'data' => $records->map(fn ($record) => [
                'value' => $record->id,
                'label' => $record->{$reference} ?? ('#'.$record->id),
            ])->all(),
        ]);
    }

    /**
     * AJAX: tickets linked to a specific object (used by detail-page panels).
     */
    public function forObject(Request $request): JsonResponse
    {
        $type = SupportObjectType::tryFrom((string) $request->input('object_type'));
        $objectId = (int) $request->input('object_id');

        if (! $type || ! $objectId) {
            return response()->json(['data' => [], 'can_create' => false]);
        }

        $user = $request->user();

        $query = SupportTicket::query()
            ->with(['creator', 'assignee'])
            ->where('object_type', $type->value)
            ->where('object_id', $objectId)
            ->latest('id');

        if (! $user->hasPermission(SupportPermissions::READ_ALL) && ! $user->hasPermission(SupportPermissions::MANAGE)) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)->orWhere('assigned_to', $user->id);
            });
        }

        return response()->json([
            'data' => SupportTicketResource::collection($query->get())->resolve($request),
            'can_create' => $user->hasPermission(SupportPermissions::CREATE),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Bulk-resolve object references for a page of tickets (avoids N+1).
     */
    private function attachObjectSummaries(Collection $tickets): void
    {
        $byType = $tickets
            ->filter(fn (SupportTicket $t) => $t->object_type && $t->object_id)
            ->groupBy(fn (SupportTicket $t) => $t->object_type instanceof SupportObjectType
                ? $t->object_type->value
                : (string) $t->object_type);

        $lookup = [];

        foreach ($byType as $typeValue => $group) {
            $type = SupportObjectType::tryFrom((string) $typeValue);
            if (! $type) {
                continue;
            }

            /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
            $model = $type->modelClass();
            $reference = $type->referenceColumn();
            $ids = $group->pluck('object_id')->unique()->all();

            $records = $model::query()->whereIn('id', $ids)->get(['id', $reference]);
            foreach ($records as $record) {
                $lookup[$typeValue][$record->id] = $record->{$reference} ?? ('#'.$record->id);
            }
        }

        foreach ($tickets as $ticket) {
            $typeValue = $ticket->object_type instanceof SupportObjectType
                ? $ticket->object_type->value
                : (string) $ticket->object_type;

            $ticket->object_summary = ($typeValue && $ticket->object_id && isset($lookup[$typeValue][$ticket->object_id]))
                ? ['reference' => $lookup[$typeValue][$ticket->object_id], 'url' => null]
                : null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildObjectSummary(SupportTicket $ticket): ?array
    {
        $object = $ticket->resolveObject();

        if (! $object) {
            return null;
        }

        $type = $ticket->object_type instanceof SupportObjectType
            ? $ticket->object_type
            : SupportObjectType::tryFrom((string) $ticket->object_type);

        if (! $type) {
            return null;
        }

        $reference = $object->{$type->referenceColumn()} ?? ('#'.$object->id);

        return [
            'reference' => $reference,
            'url' => route($type->routeName(), $object->id),
        ];
    }

    /**
     * Support agents that a ticket can be assigned to.
     *
     * @return array<int, array<string, mixed>>
     */
    private function agentOptions(User $user): array
    {
        if (! $user->hasPermission(SupportPermissions::ASSIGN) && ! $user->hasPermission(SupportPermissions::MANAGE)) {
            return [];
        }

        return User::query()
            ->whereHas('roles.permissions', fn ($q) => $q->whereIn('name', SupportPermissions::staffAccess()))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'name', 'email'])
            ->map(fn (User $agent) => [
                'id' => $agent->id,
                'name' => $agent->full_name,
                'email' => $agent->email,
            ])
            ->all();
    }

    /**
     * @return array{view: bool, reply: bool, assign: bool, update_status: bool, close: bool}
     */
    private function ticketActionAbilities(User $user, SupportTicket $ticket): array
    {
        return [
            'view' => $this->ticketPolicy->allows('view', $user, $ticket),
            'reply' => $this->ticketPolicy->allows('reply', $user, $ticket),
            'assign' => $this->ticketPolicy->allows('assign', $user, $ticket),
            'update_status' => $this->ticketPolicy->allows('updateStatus', $user, $ticket),
            'close' => $this->ticketPolicy->allows('close', $user, $ticket),
        ];
    }

    private function denyUnlessTicketAction(string $ability, User $user, SupportTicket $ticket): ?RedirectResponse
    {
        if ($this->ticketPolicy->allows($ability, $user, $ticket)) {
            return null;
        }

        return back()->with('error', __('support_tickets.errors.action_not_allowed'));
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();

        return [
            'create' => $user->hasPermission(SupportPermissions::CREATE),
            'read_all' => $user->hasPermission(SupportPermissions::READ_ALL) || $user->hasPermission(SupportPermissions::MANAGE),
            'assign' => $user->hasPermission(SupportPermissions::ASSIGN) || $user->hasPermission(SupportPermissions::MANAGE),
            'update_status' => $user->hasPermission(SupportPermissions::UPDATE_STATUS) || $user->hasPermission(SupportPermissions::MANAGE),
            'reply' => $user->hasPermission(SupportPermissions::REPLY),
            'close' => $user->hasPermission(SupportPermissions::CLOSE),
        ];
    }
}
