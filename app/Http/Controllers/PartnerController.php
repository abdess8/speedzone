<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePartnerRequest;
use App\Http\Requests\TestPartnerConnectionRequest;
use App\Http\Requests\UpdatePartnerRequest;
use App\Http\Resources\ApiLogResource;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use App\Services\Partners\PartnerApiException;
use App\Services\Partners\PartnerApiService;
use App\Services\Partners\PartnerDeliveryIngestionService;
use App\Services\PartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnerController extends Controller
{
    public function __construct(
        private readonly PartnerService $partners,
        private readonly PartnerApiService $partnerApi,
        private readonly PartnerDeliveryIngestionService $ingestion,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Partner::class);

        $partners = $this->partners->query($request)
            ->paginate($this->partners->perPage($request))
            ->withQueryString();

        return Inertia::render('partners/index', [
            'partners' => PartnerResource::collection($partners)->response()->getData(true),
            'filters' => $request->only(['search', 'status', 'per_page']),
            'can' => $this->abilities($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Partner::class);

        return Inertia::render('partners/create', $this->partners->formOptions());
    }

    public function store(StorePartnerRequest $request): RedirectResponse
    {
        $this->authorize('create', Partner::class);

        $data = $this->mergeLogoUpload($request);

        $partner = $this->partners->create($data);

        return redirect()
            ->route('partners.show', $partner)
            ->with('success', "Partner {$partner->name} created successfully.");
    }

    public function show(Request $request, Partner $partner): Response
    {
        $this->authorize('view', $partner);

        $partner->loadCount('orders');
        $partner->load(['receptionCity', 'cities', 'sectors.city', 'statusMappings', 'fieldMappings', 'updateFieldMappings', 'users']);

        $apiLogs = $partner->apiLogs()
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('partners/show', [
            'partner' => PartnerResource::make($partner)->resolve($request),
            'apiLogs' => ApiLogResource::collection($apiLogs)->response()->getData(true),
            'can' => $this->abilities($request),
        ]);
    }

    public function edit(Request $request, Partner $partner): Response
    {
        $this->authorize('update', $partner);

        $partner->load(['cities', 'sectors', 'statusMappings', 'fieldMappings', 'updateFieldMappings']);

        return Inertia::render('partners/edit', [
            ...$this->partners->formOptions(),
            'partner' => PartnerResource::make($partner)->resolve($request),
        ]);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): RedirectResponse
    {
        $this->authorize('update', $partner);

        $data = $this->mergeLogoUpload($request, $partner);

        $this->partners->update($partner, $data);

        return redirect()
            ->route('partners.show', $partner)
            ->with('success', 'Partner updated successfully.');
    }

    public function destroy(Request $request, Partner $partner): RedirectResponse
    {
        $this->authorize('delete', $partner);

        if (! $this->partners->canDelete($partner)) {
            return back()->with('error', 'This partner still has orders and cannot be deleted.');
        }

        $this->partners->delete($partner);

        return redirect()
            ->route('partners.index')
            ->with('success', 'Partner deleted successfully.');
    }

    /**
     * Test API connectivity from the create form (no saved partner yet).
     */
    public function testConnectionDraft(TestPartnerConnectionRequest $request): JsonResponse
    {
        $this->authorize('create', Partner::class);

        $probe = new Partner($request->validated());
        $probe->auth_type = $request->input('auth_type', 'BASIC');

        return $this->runConnectionTest($probe);
    }

    /**
     * Test API connectivity from the edit/show form (may override credentials).
     */
    public function testConnectionPreview(TestPartnerConnectionRequest $request, Partner $partner): JsonResponse
    {
        $this->authorize('update', $partner);

        $probe = $this->partners->makeConnectionProbe($partner, $request->validated());

        return $this->runConnectionTest($probe);
    }

    /**
     * Pull new deliveries from the partner API into local orders.
     */
    public function sync(Request $request, Partner $partner): JsonResponse|RedirectResponse
    {
        $this->authorize('sync', $partner);

        try {
            $stats = $this->ingestion->sync($partner, $request->user());
            $message = __('partners.sync.success', [
                'created' => $stats['created'],
                'updated' => $stats['updated'],
                'skipped' => $stats['skipped'],
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $stats,
                ]);
            }

            return back()->with('success', $message);
        } catch (PartnerApiException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();

        return [
            'create' => $user->can('create', Partner::class),
            'update' => $user->hasPermission('partners.update'),
            'delete' => $user->hasPermission('partners.delete'),
            'sync' => $user->hasPermission('partners.sync'),
            'test_connection' => $user->hasPermission('partners.update'),
            'view_orders' => $user->can('viewOrders', Partner::class),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeLogoUpload(StorePartnerRequest $request, ?Partner $partner = null): array
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($partner) {
                $this->partners->deleteLogo($partner);
            }

            $data['logo_url'] = $request->file('logo')->store('partners/logos', 'public');
        }

        unset($data['logo']);

        return $data;
    }

    private function runConnectionTest(Partner $partner): JsonResponse
    {
        try {
            $data = $this->partnerApi->testConnection($partner);

            return response()->json([
                'success' => true,
                'message' => __('partners.connection.success'),
                'data' => $data,
                'login' => $data['login'] ?? null,
            ]);
        } catch (PartnerApiException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'status_code' => $e->statusCode,
            ], 422);
        }
    }
}
