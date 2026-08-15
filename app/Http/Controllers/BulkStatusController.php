<?php

namespace App\Http\Controllers;

use App\Enums\BulkStatusEntityType;
use App\Enums\BulkStatusFailureReason;
use App\Http\Requests\BulkStatusUpdateRequest;
use App\Models\BulkStatusChangeLog;
use App\Services\BulkStatusUpdateService;
use App\Services\StatusTransitionAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The bulk status editor.
 *
 * Every endpoint here resolves what the user may do from
 * {@see StatusTransitionAccessService} rather than from anything the request
 * carries, so the screen, the scanner and the executor cannot be talked into
 * disagreeing with each other by a hand-crafted payload.
 */
class BulkStatusController extends Controller
{
    private const MAX_PAGE_SIZE = 100;

    public function __construct(
        private readonly StatusTransitionAccessService $access,
        private readonly BulkStatusUpdateService $bulk,
    ) {}

    /**
     * Step 1 and 2: what may be edited, and into which status.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($this->access->canUse($user), 403, __('bulk_status.errors.no_transition'));

        return Inertia::render('bulk-status/index', [
            'entities' => $this->access->payload($user),
            'filters' => $request->only([
                'entity_type', 'to_status', 'source_status', 'search', 'per_page', 'scan',
            ]),
            'failureReasons' => BulkStatusFailureReason::options(),
            // Flashed by store() through the redirect back to this page, so the
            // per-item report survives the round trip without a second request.
            'result' => fn () => $request->session()->get('bulkStatusResult'),
        ]);
    }

    /**
     * The same steps 1 and 2 payload, as JSON.
     *
     * Exists for the mobile quick action, which opens over whatever page the
     * user is on and therefore cannot receive it as a page prop. Fetched on
     * first open rather than shared globally: an operator who never touches the
     * feature should not pay for it on every navigation.
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'entities' => $this->access->payload($request->user()),
        ]);
    }

    /**
     * Step 3 and 4: the eligible board for a chosen target status.
     */
    public function items(Request $request): JsonResponse
    {
        $user = $request->user();
        $entity = $this->entity($request);
        $target = $this->target($request, $entity);

        $items = $this->bulk->eligible($request, $user, $entity, $target, $this->perPage($request));

        return response()->json([
            'data' => $items->getCollection()
                ->map(fn (Model $model) => $this->bulk->present($model, $entity, $target))
                ->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            // The board never offers a source the user cannot act on, so the
            // filter dropdown is built from the same list the query used.
            'source_options' => array_map(
                fn (string $status): array => $entity->statusDescriptor($status),
                $this->access->sources($user, $entity, $target)
            ),
        ]);
    }

    /**
     * Resolve one scanned QR code against the same rules as the manual board.
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'scan' => ['required', 'string', 'max:255'],
        ]);

        $entity = $this->entity($request);

        return response()->json($this->bulk->resolveScan(
            $request->user(),
            $entity,
            $this->target($request, $entity),
            $request->string('scan')->toString(),
        ));
    }

    /**
     * Step 6: apply the batch and report it item by item.
     */
    public function store(BulkStatusUpdateRequest $request): RedirectResponse
    {
        $entity = $request->entity();

        $result = $this->bulk->execute(
            $request->user(),
            $entity,
            $request->string('to_status')->toString(),
            $request->items(),
            $request->input('comment'),
            $request->input('source') === BulkStatusChangeLog::SOURCE_QR_SCAN
                ? BulkStatusChangeLog::SOURCE_QR_SCAN
                : BulkStatusChangeLog::SOURCE_BULK_EDIT,
        );

        $level = match (true) {
            $result['failed'] === 0 => 'success',
            $result['succeeded'] === 0 => 'error',
            default => 'warning',
        };

        return back()
            ->with($level, __('bulk_status.flash.'.$level, [
                'succeeded' => $result['succeeded'],
                'failed' => $result['failed'],
            ]))
            ->with('bulkStatusResult', $result);
    }

    private function entity(Request $request): BulkStatusEntityType
    {
        $entity = BulkStatusEntityType::tryFrom($request->string('entity_type')->toString());

        abort_if($entity === null, 422, __('bulk_status.errors.unknown_entity'));
        abort_unless(
            $this->access->canUse($request->user(), $entity),
            403,
            __('bulk_status.errors.no_transition')
        );

        return $entity;
    }

    private function target(Request $request, BulkStatusEntityType $entity): string
    {
        $target = $request->string('to_status')->toString();

        abort_unless(
            in_array($target, $this->access->targets($request->user(), $entity), true),
            403,
            __('bulk_status.errors.target_forbidden')
        );

        return $target;
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', 25);

        return $perPage < 1 ? 25 : min($perPage, self::MAX_PAGE_SIZE);
    }
}
